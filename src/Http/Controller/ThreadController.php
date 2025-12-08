<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Application\Content\BbcodeParser;
use Fred\Application\Content\LinkPreviewer;
use Fred\Application\Content\MentionService;
use Fred\Application\Content\UploadService;
use Fred\Application\Content\EmoticonSet;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Http\Navigation\CommunityContext;
use Fred\Domain\Forum\Post;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\Database\AttachmentRepository;
use Fred\Infrastructure\Database\ReactionRepository;
use Fred\Infrastructure\Database\MentionNotificationRepository;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;
use PDO;
use Throwable;

use function array_filter;
use function array_map;
use function array_slice;
use function explode;
use function json_encode;
use function trim;

final readonly class ThreadController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private PermissionService $permissions,
        private CommunityContext $communityContext,
        private CategoryRepository $categories,
        private BoardRepository $boards,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private BbcodeParser $parser,
        private LinkPreviewer $linkPreviewer,
        private UserRepository $users,
        private ProfileRepository $profiles,
        private UploadService $uploads,
        private AttachmentRepository $attachments,
        private ReactionRepository $reactions,
        private MentionNotificationRepository $mentionNotifications,
        private EmoticonSet $emoticons,
        private MentionService $mentions,
        private PDO $pdo,
    ) {
    }

    public function show(Request $request): Response
    {
        $community = $request->attribute('community');
        $thread = $request->attribute('thread');
        $board = $request->attribute('board');
        $category = $request->attribute('category');

        if (!$community instanceof Community || $thread === null || !$board instanceof Board || !$category instanceof Category) {
            return $this->notFound($request, 'Required attributes missing in ThreadController::show');
        }

        $page = (int) ($request->query['page'] ?? 1);
        $page = $page < 1 ? 1 : $page;
        $perPage = 25;
        $totalPosts = $this->posts->countByThreadId($thread->id);
        $totalPages = $totalPosts === 0 ? 1 : (int) ceil($totalPosts / $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $structure = $this->structureForCommunity($community);
        $posts = $this->posts->listByThreadIdPaginated($thread->id, $perPage, $offset);

        $postIds = array_map(static fn ($p) => $p->id, $posts);
        $authorIds = array_unique(array_map(static fn (Post $p) => $p->authorId, $posts));

        $attachmentsByPost = $this->attachments->listByPostIds($postIds);
        $reactionsByPost = $this->reactions->listByPostIds($postIds);
        $reactionUsersByPost = $this->reactions->listUsersByPostIds($postIds);
        $mentionsByPost = $this->mentionNotifications->listForPosts($postIds);

        $usersById = $this->users->findByIds($authorIds);
        $profilesByUser = $this->profiles->listByUsersInCommunity($authorIds, $community->id);

        $linkPreviewsByPost = [];
        $linkPreviewUrlsByPost = [];
        foreach ($posts as $post) {
            $urls = $this->linkPreviewer->extractUrls($post->bodyRaw ?? '', 3);
            if ($urls !== []) {
                $linkPreviewUrlsByPost[$post->id] = $urls;
            }
        }
        $currentUser = $this->auth->currentUser();
        $userReactions = !$currentUser->isGuest()
            ? $this->reactions->listUserReactions($postIds, $currentUser->id ?? 0)
            : [];
        $reportNotice = isset($request->query['reported']) ? 'Thank you. A moderator will review this post.' : null;
        $reportError = isset($request->query['report_error']) ? 'Report could not be submitted. Reason is required.' : null;

        $ctx = ViewContext::make()
            ->set('pageTitle', $thread->title)
            ->set('community', $community)
            ->set('board', $board)
            ->set('category', $category)
            ->set('thread', $thread)
            ->set('posts', $posts)
            ->set('usersById', $usersById)
            ->set('profilesByUserId', $profilesByUser)
            ->set('attachmentsByPost', $attachmentsByPost)
            ->set('reactionsByPost', $reactionsByPost)
            ->set('reactionUsersByPost', $reactionUsersByPost)
            ->set('mentionsByPost', $mentionsByPost)
            ->set('linkPreviewsByPost', $linkPreviewsByPost)
            ->set('linkPreviewUrlsByPost', $linkPreviewUrlsByPost)
            ->set('userReactions', $userReactions)
            ->set('emoticons', $this->emoticons->all())
            ->set('emoticonMap', $this->emoticons->urlsByCode())
            ->set('emoticonVersion', $this->emoticons->versionSuffix())
            ->set('totalPosts', $totalPosts)
            ->set('pagination', [
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
            ])
            ->set('currentCommunity', $community)
            ->set('canModerate', $this->permissions->canModerate($currentUser, $community->id))
            ->set('canLockThread', $this->permissions->canLockThread($currentUser, $community->id))
            ->set('canStickyThread', $this->permissions->canStickyThread($currentUser, $community->id))
            ->set('canMoveThread', $this->permissions->canMoveThread($currentUser, $community->id))
            ->set('canEditAnyPost', $this->permissions->canEditAnyPost($currentUser, $community->id))
            ->set('canDeleteAnyPost', $this->permissions->canDeleteAnyPost($currentUser, $community->id))
            ->set('canBanUsers', $this->permissions->canBan($currentUser, $community->id))
            ->set('allBoards', $structure['boards'])
            ->set('navSections', $this->communityContext->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
            ))
            ->set('customCss', trim(($community->customCss ?? '') . "\n" . ($board->customCss ?? '')))
            ->set('reportNotice', $reportNotice)
            ->set('reportError', $reportError);

        return Response::view($this->view, 'pages/thread/show.php', $ctx);
    }

    public function previews(Request $request): Response
    {
        $community = $request->attribute('community');
        $thread = $request->attribute('thread');

        if (!$community instanceof Community || $thread === null) {
            return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'Invalid context']));
        }

        $postsParam = (string) ($request->query['posts'] ?? '');
        $postIds = array_filter(array_map('intval', array_slice(explode(',', $postsParam), 0, 25)), static fn (int $id) => $id > 0);

        if ($postIds === []) {
            return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'No posts requested']));
        }

        $previews = [];

        foreach ($postIds as $postId) {
            $post = $this->posts->findById($postId);
            if ($post === null || $post->threadId !== $thread->id) {
                continue;
            }

            $items = $this->linkPreviewer->previewsForText($post->bodyRaw ?? '', 3);
            if ($items === []) {
                continue;
            }

            $previews[] = [
                'postId' => $post->id,
                'previews' => $items,
            ];
        }

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'application/json'],
            body: json_encode(['previews' => $previews]),
        );
    }

    public function create(Request $request): Response
    {
        $community = $request->attribute('community');
        $board = $request->attribute('board');

        if (!$community instanceof Community || !$board instanceof Board) {
            return $this->notFound($request, 'Required attributes missing in ThreadController::create');
        }

        $currentUser = $this->auth->currentUser();

        if (!$this->permissions->canCreateThread($currentUser)) {
            return $this->renderCreate($request, $community, $board, ['You do not have permission to create threads.'], [], 403);
        }

        return $this->renderCreate($request, $community, $board, []);
    }

    public function store(Request $request): Response
    {
        $community = $request->attribute('community');
        $board = $request->attribute('board');

        if (!$community instanceof Community || !$board instanceof Board) {
            return $this->notFound($request, 'Required attributes missing in ThreadController::store');
        }

        $currentUser = $this->auth->currentUser();

        if (!$this->permissions->canCreateThread($currentUser)) {
            return $this->renderCreate(
                $request,
                $community,
                $board,
                ['You do not have permission to create threads.'],
                [
                    'title' => $request->body['title'] ?? '',
                    'body' => $request->body['body'] ?? '',
                ],
                403,
            );
        }

        if ($board->isLocked) {
            return $this->renderCreate($request, $community, $board, ['Board is locked.'], [
                'title' => $request->body['title'] ?? '',
                'body' => $request->body['body'] ?? '',
            ], 403);
        }

        $title = trim((string) ($request->body['title'] ?? ''));
        $bodyText = trim((string) ($request->body['body'] ?? ''));
        $attachmentFile = $request->files['attachment'] ?? null;

        $errors = [];
        if ($title === '') {
            $errors[] = 'Title is required.';
        }

        if ($bodyText === '') {
            $errors[] = 'Body is required.';
        }

        if ($errors !== []) {
            return $this->renderCreate($request, $community, $board, $errors, [
                'title' => $title,
                'body' => $bodyText,
            ], 422);
        }

        $attachmentPath = null;

        try {
            $this->pdo->beginTransaction();

            if (\is_array($attachmentFile) && ($attachmentFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $attachmentPath = $this->uploads->saveAttachment($attachmentFile);
            }

            $timestamp = time();
            $thread = $this->threads->create(
                communityId: $community->id,
                boardId: $board->id,
                title: $title,
                authorId: $currentUser->id ?? 0,
                isSticky: false,
                isLocked: false,
                isAnnouncement: false,
                timestamp: $timestamp,
            );

            $profile = $currentUser->id !== null ? $this->profiles->findByUserAndCommunity($currentUser->id, $community->id) : null;

            $post = $this->posts->create(
                communityId: $community->id,
                threadId: $thread->id,
                authorId: $currentUser->id ?? 0,
                bodyRaw: $bodyText,
                bodyParsed: $this->parser->parse($bodyText, $community->slug),
                signatureSnapshot: $profile?->signatureParsed,
                timestamp: $timestamp,
            );

            if ($attachmentPath !== null) {
                $this->attachments->create(
                    communityId: $community->id,
                    postId: $post->id,
                    userId: $currentUser->id ?? 0,
                    path: $attachmentPath,
                    originalName: (string) ($attachmentFile['name'] ?? ''),
                    mimeType: (string) ($attachmentFile['type'] ?? ''),
                    sizeBytes: (int) ($attachmentFile['size'] ?? 0),
                    createdAt: $timestamp,
                );
            }

            $this->mentions->notifyFromText(
                communityId: $community->id,
                postId: $post->id,
                authorId: $currentUser->id ?? 0,
                bodyRaw: $bodyText,
            );

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($attachmentPath !== null) {
                $this->uploads->delete($attachmentPath);
            }

            return $this->renderCreate(
                $request,
                $community,
                $board,
                ['Could not create thread. Please try again.'],
                [
                    'title' => $title,
                    'body' => $bodyText,
                ],
                500,
            );
        }

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id);
    }

    private function renderCreate(
        Request $request,
        Community $community,
        Board $board,
        array $errors,
        array $old = [],
        int $status = 200,
    ): Response {
        $structure = $this->structureForCommunity($community);

        $ctx = ViewContext::make()
            ->set('pageTitle', 'New thread')
            ->set('community', $community)
            ->set('board', $board)
            ->set('errors', $errors)
            ->set('old', $old)
            ->set('currentCommunity', $community)
            ->set('navSections', $this->communityContext->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
            ))
            ->set('customCss', trim(($community->customCss ?? '') . "\n" . ($board->customCss ?? '')));

        return Response::view($this->view, 'pages/thread/create.php', $ctx, status: $status);
    }

    private function notFound(Request $request, ?string $context = null): Response
    {
        return Response::notFound(
            view: $this->view,
            config: $this->config,
            auth: $this->auth,
            request: $request,
            context: $context,
        );
    }

    private function structureForCommunity(Community $community): array
    {
        $categories = $this->categories->listByCommunityId($community->id);
        $boards = $this->boards->listByCommunityId($community->id);

        return [
            'categories' => $categories,
            'boards' => $boards,
            'boardsByCategory' => $this->groupBoards($boards),
        ];
    }

    /** @param Board[] $boards @return array<int, Board[]> */
    private function groupBoards(array $boards): array
    {
        $grouped = [];
        foreach ($boards as $board) {
            $grouped[$board->categoryId][] = $board;
        }

        foreach ($grouped as $categoryId => $items) {
            $grouped[$categoryId] = array_values($items);
        }

        return $grouped;
    }
}
