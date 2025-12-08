<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Content\EmoticonSet;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\ReactionRepository;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ReactionController
{
    public function __construct(
        private AuthService $auth,
        private AppConfig $config,
        private ViewRenderer $view,
        private CommunityHelper $communityHelper,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private ReactionRepository $reactions,
        private EmoticonSet $emoticons,
    ) {
    }

    public function add(Request $request): Response
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

        $thread = $this->threads->findById($post->threadId);
        if ($thread === null || $thread->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $board = $this->communityHelper->resolveBoard($community, (string) $thread->boardId);
        if ($board === null) {
            return $this->notFound($request);
        }

        if ($thread->isLocked || $board->isLocked) {
            $page = isset($request->body['page']) ? '?page=' . (int) $request->body['page'] : '';
            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $page . '#post-' . $postId);
        }

        $emoticon = strtolower((string) ($request->body['emoticon'] ?? ''));
        $remove = isset($request->body['remove']) && (string) $request->body['remove'] === '1';
        $pageSuffix = isset($request->body['page']) ? '?page=' . (int) $request->body['page'] . '#post-' : '?#post-';

        if ($remove) {
            $this->reactions->removeUserReaction($postId, $currentUser->id ?? 0);

            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $pageSuffix . $postId);
        }

        if ($emoticon === '' || !$this->emoticons->isAllowed($emoticon)) {
            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $pageSuffix . $postId);
        }

        $this->reactions->setUserReaction(
            communityId: $community->id,
            postId: $postId,
            userId: $currentUser->id ?? 0,
            emoticon: $emoticon,
        );

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $pageSuffix . $postId);
    }

    private function notFound(Request $request): Response
    {
        return Response::notFound(
            view: $this->view,
            request: $request,
        );
    }
}
