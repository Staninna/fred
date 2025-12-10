<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Application\Content\BbcodeParser;
use Fred\Application\Content\DeletePostService;
use Fred\Application\Content\EditPostService;
use Fred\Application\Content\MentionService;
use Fred\Application\Content\MoveThreadService;
use Fred\Application\Content\ReportPostService;
use Fred\Application\Content\ThreadStateService;
use Fred\Application\Content\UploadService;
use Fred\Application\Moderation\CreateBanService;
use Fred\Application\Moderation\DeleteBanService;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Post as ForumPost;
use Fred\Domain\Forum\Thread;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\AttachmentRepository;
use Fred\Infrastructure\Database\BanRepository;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ReportRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;
use RuntimeException;

use function trim;

final readonly class ModerationController extends Controller
{
    public function __construct(
        ViewRenderer $view,
        AppConfig $config,
        AuthService $auth,
        CommunityContext $communityContext,
        private PermissionService $permissions,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private BbcodeParser $parser,
        private UserRepository $users,
        private BanRepository $bans,
        private BoardRepository $boards,
        private CategoryRepository $categories,
        private ReportRepository $reports,
        private AttachmentRepository $attachments,
        private UploadService $uploads,
        private MentionService $mentions,
        private ThreadStateService $threadStateService,
        private EditPostService $editPostService,
        private DeletePostService $deletePostService,
        private MoveThreadService $moveThreadService,
        private ReportPostService $reportPostService,
        private CreateBanService $createBanService,
        private DeleteBanService $deleteBanService,
    ) {
        parent::__construct($view, $config, $auth, $communityContext);
    }

    public function lockThread(Request $request): Response
    {
        return $this->toggleLock($request, true);
    }

    public function unlockThread(Request $request): Response
    {
        return $this->toggleLock($request, false);
    }

    public function stickyThread(Request $request): Response
    {
        return $this->toggleSticky($request, true);
    }

    public function unstickyThread(Request $request): Response
    {
        return $this->toggleSticky($request, false);
    }

    public function announceThread(Request $request): Response
    {
        return $this->toggleAnnouncement($request, true);
    }

    public function unannounceThread(Request $request): Response
    {
        return $this->toggleAnnouncement($request, false);
    }

    public function deletePost(Request $request): Response
    {
        $ctxRequest = $request->context();
        $community = $ctxRequest->community;
        $post = $ctxRequest->post;
        $thread = $ctxRequest->thread;

        if (!$community instanceof Community || !$post instanceof ForumPost || !$thread instanceof Thread) {
            return $this->notFound($request, 'Required attributes missing in ModerationController::deletePost');
        }

        try {
            $this->deletePostService->delete($this->auth->currentUser(), $community, $post);
        } catch (RuntimeException $e) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $page = isset($request->query['page']) ? '?page=' . (int) $request->query['page'] : '';

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $page);
    }

    public function editPost(Request $request): Response
    {
        $context = $request->context();
        $community = $context->community;
        $post = $context->post;
        $thread = $context->thread;

        if (!$community instanceof Community || !$post instanceof ForumPost || !$thread instanceof Thread) {
            return $this->notFound($request, 'Required attributes missing in ModerationController::editPost');
        }

        if ($request->method === 'GET') {
            if (!$this->permissions->canEditAnyPost($this->auth->currentUser(), $community->id)) {
                return $this->forbidden();
            }
            $structure = $this->structureForCommunity($community);

            $ctx = ViewContext::make()
                ->set('pageTitle', 'Edit post')
                ->set('community', $community)
                ->set('post', $post)
                ->set('currentUser', $this->auth->currentUser())
                ->set('environment', $this->config->environment)
                ->set('activePath', $request->path)
                ->set('errors', [])
                ->set('currentCommunity', $community)
                ->set('page', (int) ($request->query['page'] ?? 1))
                ->set('navSections', $this->communityContext->navSections(
                    $community,
                    $structure['categories'],
                    $structure['boardsByCategory'],
                ))
                ->set('customCss', trim((string) ($community->customCss ?? '')));

            return Response::view($this->view, 'pages/moderation/edit_post.php', $ctx);
        }

        $bodyRaw = trim((string) ($request->body['body'] ?? ''));
        $page = isset($request->body['page']) ? (int) $request->body['page'] : 1;

        try {
            $this->editPostService->edit($this->auth->currentUser(), $community, $post, $bodyRaw);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'User cannot edit posts') {
                return $this->forbidden();
            }
            // Body is required or other error - just redirect without error
            $pageStr = $page > 1 ? '?page=' . $page : '';

            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $pageStr);
        }

        $pageStr = $page > 1 ? '?page=' . $page . '#post-' : '?#post-';

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $pageStr . $post->id);
    }

    public function moveThread(Request $request): Response
    {
        $ctxRequest = $request->context();
        $community = $ctxRequest->community;
        $thread = $ctxRequest->thread;

        if (!$community instanceof Community || !$thread instanceof Thread) {
            return $this->notFound($request, 'Required attributes missing in ModerationController::moveThread');
        }

        $targetBoardSlug = (string) ($request->body['target_board'] ?? '');
        $targetBoard = $this->communityContext->resolveBoard($community, $targetBoardSlug);

        if ($targetBoard === null) {
            return $this->notFound($request, 'Target board not found: ' . $targetBoardSlug);
        }

        try {
            $this->moveThreadService->move($this->auth->currentUser(), $community, $thread->id, $targetBoard->id);
        } catch (RuntimeException $e) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id);
    }

    public function reportPost(Request $request): Response
    {
        $ctxRequest = $request->context();
        $community = $ctxRequest->community;
        $post = $ctxRequest->post;
        $thread = $ctxRequest->thread;

        if (!$community instanceof Community || !$post instanceof ForumPost || !$thread instanceof Thread) {
            return $this->notFound($request, 'Required attributes missing in ModerationController::reportPost');
        }

        $currentUser = $this->auth->currentUser();
        $reason = trim((string) ($request->body['reason'] ?? ''));
        $page = isset($request->body['page']) ? '&page=' . (int) $request->body['page'] : '';

        try {
            $this->reportPostService->report($currentUser, $community->id, $post->id, $reason);

            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . '?reported=1' . $page . '#post-' . $post->id);
        } catch (RuntimeException $e) {
            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . '?report_error=1' . $page . '#post-' . $post->id);
        }
    }

    public function listBans(Request $request): Response
    {
        $community = $request->context()->community;

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in ModerationController::listBans');
        }

        if (!$this->permissions->canBan($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $bans = $this->bans->listAll();
        $usernames = $this->users->listUsernames();

        $ctx = ViewContext::make()
            ->set('pageTitle', 'Bans')
            ->set('bans', $bans)
            ->set('environment', $this->config->environment)
            ->set('currentUser', $this->auth->currentUser())
            ->set('currentCommunity', $community)
            ->set('activePath', $request->path)
            ->set('errors', [])
            ->set('old', [])
            ->set('navSections', $this->communityContext->navForCommunity($community))
            ->set('usernames', $usernames)
            ->set('customCss', trim((string) ($community->customCss ?? '')));

        return Response::view($this->view, 'pages/moderation/bans.php', $ctx);
    }

    public function createBan(Request $request): Response
    {
        $community = $request->context()->community;

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in ModerationController::createBan');
        }

        if (!$this->permissions->canBan($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $username = trim((string) ($request->body['username'] ?? ''));
        $reason = trim((string) ($request->body['reason'] ?? ''));
        $expires = trim((string) ($request->body['expires_at'] ?? ''));

        $errors = [];

        try {
            $this->createBanService->create($username, $reason, $expires !== '' ? $expires : null);

            return Response::redirect('/c/' . $community->slug . '/admin/bans');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        $bans = $this->bans->listAll();
        $usernames = $this->users->listUsernames();

        $ctx = ViewContext::make()
            ->set('pageTitle', 'Bans')
            ->set('bans', $bans)
            ->set('environment', $this->config->environment)
            ->set('currentUser', $this->auth->currentUser())
            ->set('currentCommunity', $community)
            ->set('activePath', $request->path)
            ->set('errors', $errors)
            ->set('old', [
                'username' => $username,
                'reason' => $reason,
                'expires_at' => $expires,
            ])
            ->set('navSections', $this->communityContext->navForCommunity($community))
            ->set('usernames', $usernames)
            ->set('customCss', trim((string) ($community->customCss ?? '')));

        return Response::view($this->view, 'pages/moderation/bans.php', $ctx, status: 422);
    }

    public function deleteBan(Request $request): Response
    {
        $community = $request->context()->community;

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in ModerationController::deleteBan');
        }

        if (!$this->permissions->canBan($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $banId = (int) ($request->params['ban'] ?? 0);

        try {
            $this->deleteBanService->delete($banId);
        } catch (RuntimeException $e) {
            // Silently ignore - ban may not exist or invalid ID
        }

        return Response::redirect('/c/' . $community->slug . '/admin/bans');
    }

    private function toggleLock(Request $request, bool $lock): Response
    {
        $context = $request->context();
        $community = $context->community;
        $thread = $context->thread;

        if (!$community instanceof Community || !$thread instanceof Thread) {
            return $this->notFound($request, 'Required attributes missing in ModerationController::toggleLock');
        }

        try {
            $this->threadStateService->setLock($this->auth->currentUser(), $community, $thread, $lock);
        } catch (RuntimeException $e) {
            return $this->forbidden();
        }

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id);
    }

    private function toggleSticky(Request $request, bool $sticky): Response
    {
        $context = $request->context();
        $community = $context->community;
        $thread = $context->thread;

        if (!$community instanceof Community || !$thread instanceof Thread) {
            return $this->notFound($request, 'Required attributes missing in ModerationController::toggleSticky');
        }

        try {
            $this->threadStateService->setSticky($this->auth->currentUser(), $community, $thread, $sticky);
        } catch (RuntimeException $e) {
            return $this->forbidden();
        }

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id);
    }

    private function toggleAnnouncement(Request $request, bool $announcement): Response
    {
        $context = $request->context();
        $community = $context->community;
        $thread = $context->thread;

        if (!$community instanceof Community || !$thread instanceof Thread) {
            return $this->notFound($request, 'Required attributes missing in ModerationController::toggleAnnouncement');
        }

        try {
            $this->threadStateService->setAnnouncement($this->auth->currentUser(), $community, $thread, $announcement);
        } catch (RuntimeException $e) {
            return $this->forbidden();
        }

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id);
    }



    private function structureForCommunity(Community $community): array
    {
        $categories = $this->categories->listByCommunityId($community->id);
        $boards = $this->boards->listByCommunityId($community->id);

        return [
            'categories' => $categories,
            'boardsByCategory' => $this->groupBoards($boards),
        ];
    }

    /** @param Board[] $boards @return array<int, \Fred\Domain\Community\Board[]> */
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
