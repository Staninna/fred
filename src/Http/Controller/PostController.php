<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Content\CreateReplyService;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewRenderer;

use function is_array;

use RuntimeException;

use function str_contains;
use function trim;

final readonly class PostController extends Controller
{
    public function __construct(
        ViewRenderer $view,
        AppConfig $config,
        AuthService $auth,
        CommunityContext $communityContext,
        private CreateReplyService $createReplyService,
    ) {
        parent::__construct($view, $config, $auth, $communityContext);
    }

    public function store(Request $request): Response
    {
        $context = $request->context();
        $community = $context->community;
        $thread = $context->thread;
        $board = $context->board;

        if (!$community instanceof Community || $thread === null || !$board instanceof Board) {
            return $this->notFound($request, 'Required attributes missing in PostController::store');
        }

        $currentUser = $context->currentUser ?? $this->auth->currentUser();

        if ($board->isLocked) {
            return new Response(
                status: 403,
                headers: ['Content-Type' => 'text/html; charset=utf-8'],
                body: 'Thread is locked.',
            );
        }

        $bodyText = trim((string) ($request->body['body'] ?? ''));

        if ($bodyText === '') {
            $page = isset($request->body['page']) ? '?page=' . (int) $request->body['page'] : '';

            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $page);
        }

        try {
            $attachmentFile = $request->files['attachment'] ?? null;
            $result = $this->createReplyService->create(
                currentUser: $currentUser,
                community: $community,
                thread: $thread,
                bodyText: $bodyText,
                attachmentFile: is_array($attachmentFile) ? $attachmentFile : null,
            );

            $page = isset($request->body['page']) ? '?page=' . (int) $request->body['page'] . '#post-' : '?#post-';

            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $page . $result['post']->id);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'User cannot reply') {
                return $this->forbidden();
            }

            if (str_contains($e->getMessage(), 'Attachment error')) {
                return new Response(
                    status: 422,
                    headers: ['Content-Type' => 'text/plain; charset=utf-8'],
                    body: $e->getMessage(),
                );
            }

            if ($e->getMessage() === 'Thread is locked') {
                return new Response(
                    status: 403,
                    headers: ['Content-Type' => 'text/html; charset=utf-8'],
                    body: 'Thread is locked.',
                );
            }

            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id);
        }
    }


}
