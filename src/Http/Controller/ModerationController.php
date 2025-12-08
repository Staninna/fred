<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Application\Content\MentionService;
use Fred\Application\Content\UploadService;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Post as ForumPost;
use Fred\Domain\Forum\Thread;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\BanRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\Database\ReportRepository;
use Fred\Infrastructure\Database\AttachmentRepository;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ModerationController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private PermissionService $permissions,
        private CommunityContext $communityContext,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private \Fred\Application\Content\BbcodeParser $parser,
        private UserRepository $users,
        private BanRepository $bans,
        private BoardRepository $boards,
        private CategoryRepository $categories,
        private ReportRepository $reports,
        private AttachmentRepository $attachments,
        private UploadService $uploads,
        private MentionService $mentions,
    ) {
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
        $community = $request->attribute('community');
        $post = $request->attribute('post');
        $thread = $request->attribute('thread');

        if (!$community instanceof Community || !$post instanceof ForumPost || !$thread instanceof Thread) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canDeleteAnyPost($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $attachments = $this->attachments->listByPostId($post->id);
        foreach ($attachments as $attachment) {
            $this->uploads->delete($attachment->path);
        }

        $this->posts->delete($post->id);

        $page = isset($request->query['page']) ? '?page=' . (int) $request->query['page'] : '';
        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $page);
    }

    public function editPost(Request $request): Response
    {
        $community = $request->attribute('community');
        $post = $request->attribute('post');
        $thread = $request->attribute('thread');

        if (!$community instanceof Community || !$post instanceof ForumPost || !$thread instanceof Thread) {
            return $this->notFound($request);
        }

        if ($request->method === 'GET') {
            if (!$this->permissions->canEditAnyPost($this->auth->currentUser(), $community->id)) {
                return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
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

        if (!$this->permissions->canEditAnyPost($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $bodyRaw = trim((string) ($request->body['body'] ?? ''));
        if ($bodyRaw === '') {
            $page = isset($request->body['page']) ? '?page=' . (int) $request->body['page'] : '';
            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $page);
        }

        $this->posts->updateBody(
            id: $post->id,
            raw: $bodyRaw,
            parsed: $this->parser->parse($bodyRaw, $community->slug),
            timestamp: time(),
        );

        $this->mentions->notifyFromText(
            communityId: $community->id,
            postId: $post->id,
            authorId: $this->auth->currentUser()->id ?? $post->authorId,
            bodyRaw: $bodyRaw,
        );

        $page = isset($request->body['page']) ? '?page=' . (int) $request->body['page'] . '#post-' : '?#post-';
        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $page . $post->id);
    }

    public function moveThread(Request $request): Response
    {
        $community = $request->attribute('community');
        $thread = $request->attribute('thread');

        if (!$community instanceof Community || !$thread instanceof Thread) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canMoveThread($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $targetBoardSlug = (string) ($request->body['target_board'] ?? '');
        $targetBoard = $this->communityContext->resolveBoard($community, $targetBoardSlug);
        if ($targetBoard === null) {
            return $this->notFound($request);
        }

        $this->threads->moveToBoard($thread->id, $targetBoard->id);

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id);
    }

    public function reportPost(Request $request): Response
    {
        $community = $request->attribute('community');
        $post = $request->attribute('post');
        $thread = $request->attribute('thread');

        if (!$community instanceof Community || !$post instanceof ForumPost || !$thread instanceof Thread) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();

        $reason = trim((string) ($request->body['reason'] ?? ''));
        if ($reason === '' || \strlen($reason) > 500) {
            $page = isset($request->body['page']) ? '&page=' . (int) $request->body['page'] : '';
            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . '?report_error=1' . $page . '#post-' . $post->id);
        }

        $this->reports->create(
            communityId: $community->id,
            postId: $post->id,
            reporterId: $currentUser->id ?? 0,
            reason: $reason,
            timestamp: time(),
        );

        $page = isset($request->body['page']) ? '&page=' . (int) $request->body['page'] : '';
        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . '?reported=1' . $page . '#post-' . $post->id);
    }

    public function listBans(Request $request): Response
    {
        $community = $request->attribute('community');
        if (!$community instanceof Community) {
            return $this->notFound($request);
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
        $community = $request->attribute('community');
        if (!$community instanceof Community) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canBan($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $username = trim((string) ($request->body['username'] ?? ''));
        $reason = trim((string) ($request->body['reason'] ?? ''));
        $expires = trim((string) ($request->body['expires_at'] ?? ''));

        $errors = [];
        if ($username === '') {
            $errors[] = 'Username is required.';
        }
        if ($reason === '') {
            $errors[] = 'Reason is required.';
        }

        $expiresAt = null;
        if ($expires !== '') {
            $expiresAt = strtotime($expires) ?: null;
        }

        $user = $username !== '' ? $this->users->findByUsername($username) : null;
        if ($user === null) {
            $errors[] = 'User not found.';
        }

        if ($errors === []) {
            $this->bans->create(
                userId: $user->id,
                reason: $reason,
                expiresAt: $expiresAt,
                timestamp: time(),
            );

            return Response::redirect('/c/' . $community->slug . '/admin/bans');
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
        $community = $request->attribute('community');
        if (!$community instanceof Community) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canBan($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $banId = (int) ($request->params['ban'] ?? 0);
        if ($banId > 0) {
            $this->bans->delete($banId);
        }

        return Response::redirect('/c/' . $community->slug . '/admin/bans');
    }

    private function toggleLock(Request $request, bool $lock): Response
    {
        $community = $request->attribute('community');
        $thread = $request->attribute('thread');

        if (!$community instanceof Community || !$thread instanceof Thread) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canLockThread($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $this->threads->updateLock($thread->id, $lock);

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id);
    }

    private function toggleSticky(Request $request, bool $sticky): Response
    {
        $community = $request->attribute('community');
        $thread = $request->attribute('thread');

        if (!$community instanceof Community || !$thread instanceof Thread) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canStickyThread($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $this->threads->updateSticky($thread->id, $sticky);

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id);
    }

    private function toggleAnnouncement(Request $request, bool $announcement): Response
    {
        $community = $request->attribute('community');
        $thread = $request->attribute('thread');

        if (!$community instanceof Community || !$thread instanceof Thread) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $this->threads->updateAnnouncement($thread->id, $announcement);

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id);
    }

    private function notFound(Request $request): Response
    {
        return Response::notFound(
            view: $this->view,
            request: $request,
        );
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

    /** @param \Fred\Domain\Community\Board[] $boards @return array<int, \Fred\Domain\Community\Board[]> */
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
