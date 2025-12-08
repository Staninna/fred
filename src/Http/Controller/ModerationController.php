<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Application\Content\MentionService;
use Fred\Application\Content\UploadService;
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
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ModerationController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private PermissionService $permissions,
        private CommunityHelper $communityHelper,
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
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canDeleteAnyPost($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $postId = (int) ($request->params['post'] ?? 0);
        $post = $this->posts->findById($postId);
        if ($post === null || $post->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $attachments = $this->attachments->listByPostId($postId);
        foreach ($attachments as $attachment) {
            $this->uploads->delete($attachment->path);
        }

        $this->posts->delete($postId);

        $page = isset($request->query['page']) ? '?page=' . (int) $request->query['page'] : '';
        return Response::redirect('/c/' . $community->slug . '/t/' . $post->threadId . $page);
    }

    public function editPost(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if ($request->method === 'GET') {
            if (!$this->permissions->canEditAnyPost($this->auth->currentUser(), $community->id)) {
                return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
            }
            $postId = (int) ($request->params['post'] ?? 0);
            $post = $this->posts->findById($postId);
            if ($post === null || $post->communityId !== $community->id) {
                return $this->notFound($request);
            }
            $structure = $this->communityHelper->structureForCommunity($community);
            $body = $this->view->render('pages/moderation/edit_post.php', [
                'pageTitle' => 'Edit post',
                'community' => $community,
                'post' => $post,
                'currentUser' => $this->auth->currentUser(),
                'environment' => $this->config->environment,
                'activePath' => $request->path,
                'errors' => [],
                'currentCommunity' => $community,
                'page' => (int) ($request->query['page'] ?? 1),
                'navSections' => $this->communityHelper->navSections(
                    $community,
                    $structure['categories'],
                    $structure['boardsByCategory'],
                ),
                'customCss' => trim((string) ($community->customCss ?? '')),
            ]);

            return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $body);
        }

        if (!$this->permissions->canEditAnyPost($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $postId = (int) ($request->params['post'] ?? 0);
        $post = $this->posts->findById($postId);
        if ($post === null || $post->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $bodyRaw = trim((string) ($request->body['body'] ?? ''));
        if ($bodyRaw === '') {
            $page = isset($request->body['page']) ? '?page=' . (int) $request->body['page'] : '';
            return Response::redirect('/c/' . $community->slug . '/t/' . $post->threadId . $page);
        }

        $this->posts->updateBody(
            id: $postId,
            raw: $bodyRaw,
            parsed: $this->parser->parse($bodyRaw),
            timestamp: time(),
        );

        $this->mentions->notifyFromText(
            communityId: $community->id,
            postId: $postId,
            authorId: $this->auth->currentUser()->id ?? $post->authorId,
            bodyRaw: $bodyRaw,
        );

        $page = isset($request->body['page']) ? '?page=' . (int) $request->body['page'] . '#post-' : '?#post-';
        return Response::redirect('/c/' . $community->slug . '/t/' . $post->threadId . $page . $postId);
    }

    public function moveThread(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canMoveThread($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $threadId = (int) ($request->params['thread'] ?? 0);
        $thread = $this->threads->findById($threadId);
        if ($thread === null || $thread->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $targetBoardSlug = (string) ($request->body['target_board'] ?? '');
        $targetBoard = $this->communityHelper->resolveBoard($community, $targetBoardSlug);
        if ($targetBoard === null) {
            return $this->notFound($request);
        }

        $this->threads->moveToBoard($threadId, $targetBoard->id);

        return Response::redirect('/c/' . $community->slug . '/t/' . $threadId);
    }

    public function reportPost(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();
        if ($currentUser->isGuest()) {
            return Response::redirect('/login');
        }

        $postId = (int) ($request->params['post'] ?? 0);
        $post = $this->posts->findById($postId);
        if ($post === null || $post->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $reason = trim((string) ($request->body['reason'] ?? ''));
        if ($reason === '' || \strlen($reason) > 500) {
            $page = isset($request->body['page']) ? '&page=' . (int) $request->body['page'] : '';
            return Response::redirect('/c/' . $community->slug . '/t/' . $post->threadId . '?report_error=1' . $page . '#post-' . $postId);
        }

        $this->reports->create(
            communityId: $community->id,
            postId: $postId,
            reporterId: $currentUser->id ?? 0,
            reason: $reason,
            timestamp: time(),
        );

        $page = isset($request->body['page']) ? '&page=' . (int) $request->body['page'] : '';
        return Response::redirect('/c/' . $community->slug . '/t/' . $post->threadId . '?reported=1' . $page . '#post-' . $postId);
    }

    public function listBans(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canBan($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $bans = $this->bans->listAll();
        $usernames = $this->users->listUsernames();
        $body = $this->view->render('pages/moderation/bans.php', [
            'pageTitle' => 'Bans',
            'bans' => $bans,
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'currentCommunity' => $community,
            'activePath' => $request->path,
            'errors' => [],
            'old' => [],
            'navSections' => $this->communityHelper->navForCommunity($community),
            'usernames' => $usernames,
        ]);

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $body);
    }

    public function createBan(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
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

            return Response::redirect('/c/' . ($request->params['community'] ?? '') . '/admin/bans');
        }

        $bans = $this->bans->listAll();
        $usernames = $this->users->listUsernames();
        $body = $this->view->render('pages/moderation/bans.php', [
            'pageTitle' => 'Bans',
            'bans' => $bans,
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'currentCommunity' => $community,
            'activePath' => $request->path,
            'errors' => $errors,
            'old' => [
                'username' => $username,
                'reason' => $reason,
                'expires_at' => $expires,
            ],
            'navSections' => $this->communityHelper->navForCommunity($community),
            'usernames' => $usernames,
        ]);

        return new Response(422, ['Content-Type' => 'text/html; charset=utf-8'], $body);
    }

    public function deleteBan(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
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
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canLockThread($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $threadId = (int) ($request->params['thread'] ?? 0);
        $thread = $this->threads->findById($threadId);
        if ($thread === null || $thread->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $this->threads->updateLock($threadId, $lock);

        return Response::redirect('/c/' . $community->slug . '/t/' . $threadId);
    }

    private function toggleSticky(Request $request, bool $sticky): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canStickyThread($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $threadId = (int) ($request->params['thread'] ?? 0);
        $thread = $this->threads->findById($threadId);
        if ($thread === null || $thread->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $this->threads->updateSticky($threadId, $sticky);

        return Response::redirect('/c/' . $community->slug . '/t/' . $threadId);
    }

    private function toggleAnnouncement(Request $request, bool $announcement): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $threadId = (int) ($request->params['thread'] ?? 0);
        $thread = $this->threads->findById($threadId);
        if ($thread === null || $thread->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $this->threads->updateAnnouncement($threadId, $announcement);

        return Response::redirect('/c/' . $community->slug . '/t/' . $threadId);
    }

    private function notFound(Request $request): Response
    {
        return Response::notFound(
            view: $this->view,
            config: $this->config,
            auth: $this->auth,
            request: $request,
            navSections: $this->communityHelper->navForCommunity(),
        );
    }
}
