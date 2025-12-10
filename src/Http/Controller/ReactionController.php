<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Content\EmoticonSet;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ReactionRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ReactionController extends Controller
{
    public function __construct(
        ViewRenderer $view,
        AppConfig $config,
        AuthService $auth,
        CommunityContext $communityContext,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private ReactionRepository $reactions,
        private EmoticonSet $emoticons,
    ) {
        parent::__construct($view, $config, $auth, $communityContext);
    }

    public function add(Request $request): Response
    {
        $ctxRequest = $request->context();
        $community = $ctxRequest->community;
        $post = $ctxRequest->post;
        $thread = $ctxRequest->thread;
        $board = $ctxRequest->board;

        if (!$community instanceof Community || $post === null || $thread === null || !$board instanceof Board) {
            return $this->notFound($request, 'Required attributes missing in ReactionController::add');
        }

        $currentUser = $this->auth->currentUser();

        if ($currentUser->isGuest()) {
            return Response::redirect('/login');
        }

        $userId = (int) $currentUser->id;

        $postId = (int) $post->id;

        if ($thread->isLocked || $board->isLocked) {
            $page = isset($request->body['page']) ? '?page=' . (int) $request->body['page'] : '';

            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $page . '#post-' . $postId);
        }

        $emoticon = strtolower((string) ($request->body['emoticon'] ?? ''));
        $remove = isset($request->body['remove']) && (string) $request->body['remove'] === '1';
        $pageSuffix = isset($request->body['page']) ? '?page=' . (int) $request->body['page'] . '#post-' : '?#post-';

        if ($remove) {
            $this->reactions->removeUserReaction($postId, $userId);

            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $pageSuffix . $postId);
        }

        if ($emoticon === '' || !$this->emoticons->isAllowed($emoticon)) {
            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $pageSuffix . $postId);
        }

        $this->reactions->setUserReaction(
            communityId: $community->id,
            postId: $postId,
            userId: $userId,
            emoticon: $emoticon,
        );

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $pageSuffix . $postId);
    }


}
