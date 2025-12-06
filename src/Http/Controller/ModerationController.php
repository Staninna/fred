<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ThreadRepository;
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

    public function deletePost(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canModerate($this->auth->currentUser())) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $postId = (int) ($request->params['post'] ?? 0);
        $post = $this->posts->findById($postId);
        if ($post === null || $post->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $this->posts->delete($postId);

        return Response::redirect('/c/' . $community->slug . '/t/' . $post->threadId);
    }

    public function editPost(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if ($request->method === 'GET') {
            if (!$this->permissions->canModerate($this->auth->currentUser())) {
                return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
            }
            $postId = (int) ($request->params['post'] ?? 0);
            $post = $this->posts->findById($postId);
            if ($post === null || $post->communityId !== $community->id) {
                return $this->notFound($request);
            }
            $body = $this->view->render('pages/moderation/edit_post.php', [
                'pageTitle' => 'Edit post',
                'community' => $community,
                'post' => $post,
                'currentUser' => $this->auth->currentUser(),
                'environment' => $this->config->environment,
                'activePath' => $request->path,
                'errors' => [],
            ]);

            return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $body);
        }

        if (!$this->permissions->canModerate($this->auth->currentUser())) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $postId = (int) ($request->params['post'] ?? 0);
        $post = $this->posts->findById($postId);
        if ($post === null || $post->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $bodyRaw = trim((string) ($request->body['body'] ?? ''));
        if ($bodyRaw === '') {
            return Response::redirect('/c/' . $community->slug . '/t/' . $post->threadId);
        }

        $this->posts->updateBody(
            id: $postId,
            raw: $bodyRaw,
            parsed: $this->parser->parse($bodyRaw),
            timestamp: time(),
        );

        return Response::redirect('/c/' . $community->slug . '/t/' . $post->threadId . '#post-' . $postId);
    }

    private function toggleLock(Request $request, bool $lock): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canModerate($this->auth->currentUser())) {
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

        if (!$this->permissions->canModerate($this->auth->currentUser())) {
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
