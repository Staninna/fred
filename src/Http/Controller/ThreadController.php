<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use function array_filter;
use function array_map;
use function array_slice;
use function explode;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Application\Content\BbcodeParser;
use Fred\Application\Content\CreateThreadService;
use Fred\Application\Content\EmoticonSet;
use Fred\Application\Content\LinkPreviewer;
use Fred\Application\Content\PostReferenceValidator;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Post;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\AttachmentRepository;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\MentionNotificationRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\ReactionRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;

use function is_array;
use function json_encode;

use RuntimeException;

use function str_contains;
use function trim;

final readonly class ThreadController extends Controller
{
    public function __construct(
        ViewRenderer $view,
        AppConfig $config,
        AuthService $auth,
        CommunityContext $communityContext,
        private PermissionService $permissions,
        private CategoryRepository $categories,
        private BoardRepository $boards,
        private PostRepository $posts,
        private LinkPreviewer $linkPreviewer,
        private UserRepository $users,
        private ProfileRepository $profiles,
        private AttachmentRepository $attachments,
        private ReactionRepository $reactions,
        private MentionNotificationRepository $mentionNotifications,
        private EmoticonSet $emoticons,
        private CreateThreadService $createThreadService,
        private PostReferenceValidator $postReferenceValidator,
    ) {
        parent::__construct($view, $config, $auth, $communityContext);
    }

    public function show(Request $request): Response
    {
        $ctxRequest = $request->context();
        $community = $ctxRequest->community;
        $thread = $ctxRequest->thread;
        $board = $ctxRequest->board;
        $category = $ctxRequest->category;

        if (!$community instanceof Community || $thread === null || !$board instanceof Board || !$category instanceof Category) {
            return $this->notFound($request, 'Required attributes missing in ThreadController::show');
        }

        $page = (int) ($request->query['page'] ?? 1);
        $page = max($page, 1);
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

        // Build a map of post ID to page number for all posts in the thread
        $allThreadPosts = $this->posts->listByThreadId($thread->id);
        $postIdToPageNumber = [];
        foreach ($allThreadPosts as $index => $threadPost) {
            $postPageNumber = (int) floor($index / $perPage) + 1;
            $postIdToPageNumber[$threadPost->id] = $postPageNumber;
        }

        // Validate post references in each post's body with correct page numbers
        $validatedBodyParsed = [];
        foreach ($posts as $post) {
            if ($post->bodyParsed !== null) {
                $validatedBodyParsed[$post->id] = $this->postReferenceValidator->validate($post->bodyParsed, $postIdToPageNumber);
            }
        }

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

            if ($urls === []) {
                continue;
            }

            $linkPreviewUrlsByPost[$post->id] = $urls;

            // For the initial thread render, we only want to use already-cached
            // previews so that we don't block the response on new network calls.
            $cachedPreviews = $this->linkPreviewer->previewsFromCacheForUrls($urls);

            if ($cachedPreviews !== []) {
                $linkPreviewsByPost[$post->id] = $cachedPreviews;
            }
        }
        $currentUser = $ctxRequest->currentUser ?? $this->auth->currentUser();
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
            ->set('validatedBodyParsed', $validatedBodyParsed)
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
        $ctxRequest = $request->context();
        $community = $ctxRequest->community;
        $thread = $ctxRequest->thread;

        if (!$community instanceof Community || $thread === null) {
            return new Response(400, ['Content-Type' => 'application/json'], (string) json_encode(['error' => 'Invalid context']));
        }

        $postsParam = (string) ($request->query['posts'] ?? '');
        $postIds = array_filter(array_map('intval', array_slice(explode(',', $postsParam), 0, 25)), static fn (int $id) => $id > 0);

        if ($postIds === []) {
            return new Response(400, ['Content-Type' => 'application/json'], (string) json_encode(['error' => 'No posts requested']));
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
            body: (string) json_encode(['previews' => $previews]),
        );
    }

    public function create(Request $request): Response
    {
        $ctxRequest = $request->context();
        $community = $ctxRequest->community;
        $board = $ctxRequest->board;

        if (!$community instanceof Community || !$board instanceof Board) {
            return $this->notFound($request, 'Required attributes missing in ThreadController::create');
        }

        $currentUser = $ctxRequest->currentUser ?? $this->auth->currentUser();

        if (!$this->permissions->canCreateThread($currentUser)) {
            return $this->renderCreate($request, $community, $board, ['You do not have permission to create threads.'], [], 403);
        }

        return $this->renderCreate($request, $community, $board, []);
    }

    public function store(Request $request): Response
    {
        $context = $request->context();
        $community = $context->community;
        $board = $context->board;

        if (!$community instanceof Community || !$board instanceof Board) {
            return $this->notFound($request, 'Required attributes missing in ThreadController::store');
        }

        $currentUser = $context->currentUser ?? $this->auth->currentUser();

        $title = trim((string) ($request->body['title'] ?? ''));
        $bodyText = trim((string) ($request->body['body'] ?? ''));
        $attachmentFile = $request->files['attachment'] ?? null;

        try {
            $result = $this->createThreadService->create(
                currentUser: $currentUser,
                community: $community,
                board: $board,
                title: $title,
                bodyText: $bodyText,
                attachmentFile: is_array($attachmentFile) ? $attachmentFile : null,
            );

            return Response::redirect('/c/' . $community->slug . '/t/' . $result['thread']->id);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $status = 422;

            if ($message === 'User cannot create threads') {
                $errors = ['You do not have permission to create threads.'];
                $status = 403;
            } elseif ($message === 'Board is locked') {
                $errors = ['Board is locked.'];
                $status = 403;
            } elseif ($message === 'Title is required') {
                $errors = ['Title is required.'];
            } elseif ($message === 'Body is required') {
                $errors = ['Body is required.'];
            } elseif (str_contains($message, 'Attachment error')) {
                $errors = [$message];
            } else {
                $errors = ['Could not create thread. Please try again.'];
                $status = 500;
            }

            return $this->renderCreate($request, $community, $board, $errors, [
                'title' => $title,
                'body' => $bodyText,
            ], $status);
        }
    }

    /**
     * @param string[] $errors
     * @param array<string, mixed> $old
     */
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



    /** @return array{categories: \Fred\Domain\Community\Category[], boards: Board[], boardsByCategory: array<int, array<int, Board>>} */
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

    /**
     * @param Board[] $boards
     * @return array<int, array<int, Board>>
     */
    private function groupBoards(array $boards): array
    {
        $grouped = [];

        foreach ($boards as $board) {
            $grouped[$board->categoryId][] = $board;
        }

        return $grouped;
    }
}
