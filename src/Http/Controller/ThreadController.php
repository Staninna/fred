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
use Fred\Domain\Forum\Post;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\AttachmentRepository;
use Fred\Infrastructure\Database\ReactionRepository;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;
use PDO;
use Throwable;

use function trim;

final readonly class ThreadController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private PermissionService $permissions,
        private CommunityHelper $communityHelper,
        private CategoryRepository $categories,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private BbcodeParser $parser,
        private LinkPreviewer $linkPreviewer,
        private ProfileRepository $profiles,
        private UploadService $uploads,
        private AttachmentRepository $attachments,
        private ReactionRepository $reactions,
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
            return $this->notFound($request);
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

        $structure = $this->communityHelper->structureForCommunity($community);
        $posts = $this->posts->listByThreadIdPaginated($thread->id, $perPage, $offset);
        $attachmentsByPost = $this->attachments->listByPostIds(array_map(static fn ($p) => $p->id, $posts));
        $reactionsByPost = $this->reactions->listByPostIds(array_map(static fn ($p) => $p->id, $posts));
        $reactionUsersByPost = $this->reactions->listUsersByPostIds(array_map(static fn ($p) => $p->id, $posts));
        $linkPreviewsByPost = [];
        foreach ($posts as $post) {
            $previews = $this->linkPreviewer->previewsForText($post->bodyRaw ?? '');
            if ($previews !== []) {
                $linkPreviewsByPost[$post->id] = $previews;
            }
        }
        $authorIds = array_unique(array_map(static fn (Post $p) => $p->authorId, $posts));
        $profilesByUser = $this->profiles->listByUsersInCommunity($authorIds, $community->id);
        $currentUser = $this->auth->currentUser();
        $userReactions = !$currentUser->isGuest()
            ? $this->reactions->listUserReactions(array_map(static fn ($p) => $p->id, $posts), $currentUser->id ?? 0)
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
            ->set('profilesByUserId', $profilesByUser)
            ->set('attachmentsByPost', $attachmentsByPost)
            ->set('reactionsByPost', $reactionsByPost)
            ->set('reactionUsersByPost', $reactionUsersByPost)
            ->set('linkPreviewsByPost', $linkPreviewsByPost)
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
            ->set('navSections', $this->communityHelper->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
            ))
            ->set('customCss', trim(($community->customCss ?? '') . "\n" . ($board->customCss ?? '')))
            ->set('reportNotice', $reportNotice)
            ->set('reportError', $reportError);

        return Response::view($this->view, 'pages/thread/show.php', $ctx);
    }

    public function create(Request $request): Response
    {
        $community = $request->attribute('community');
        $board = $request->attribute('board');

        if (!$community instanceof Community || !$board instanceof Board) {
            return $this->notFound($request);
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
            return $this->notFound($request);
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
        $structure = $this->communityHelper->structureForCommunity($community);

        $ctx = ViewContext::make()
            ->set('pageTitle', 'New thread')
            ->set('community', $community)
            ->set('board', $board)
            ->set('errors', $errors)
            ->set('old', $old)
            ->set('currentCommunity', $community)
            ->set('navSections', $this->communityHelper->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
            ))
            ->set('customCss', trim(($community->customCss ?? '') . "\n" . ($board->customCss ?? '')));

        return Response::view($this->view, 'pages/thread/create.php', $ctx, status: $status);
    }

    private function notFound(Request $request): Response
    {
        return Response::notFound(
            view: $this->view,
            request: $request,
        );
    }
}
