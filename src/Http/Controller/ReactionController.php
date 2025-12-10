<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Content\AddReactionService;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewRenderer;
use RuntimeException;

final readonly class ReactionController extends Controller
{
    public function __construct(
        ViewRenderer $view,
        AppConfig $config,
        AuthService $auth,
        CommunityContext $communityContext,
        private AddReactionService $addReactionService,
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

        $postId = (int) $post->id;
        $remove = isset($request->body['remove']) && (string) $request->body['remove'] === '1';
        $pageSuffix = isset($request->body['page']) ? '?page=' . (int) $request->body['page'] . '#post-' : '?#post-';

        if ($remove) {
            try {
                $this->addReactionService->remove($currentUser, $postId);
            } catch (RuntimeException $e) {
                // Silently handle - user not logged in handled above
            }

            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $pageSuffix . $postId);
        }

        $emoticon = (string) ($request->body['emoticon'] ?? '');

        try {
            $this->addReactionService->add($currentUser, $community->id, $thread, $board, $postId, $emoticon);
        } catch (RuntimeException $e) {
            // Invalid emoticon or locked thread/board - silently redirect
        }

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $pageSuffix . $postId);
    }


}
