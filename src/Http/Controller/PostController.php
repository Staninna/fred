<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Content\BbcodeParser;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\View\ViewRenderer;

use function trim;

final readonly class PostController
{
    public function __construct(
        private AuthService $auth,
        private ViewRenderer $view,
        private AppConfig $config,
        private CommunityHelper $communityHelper,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private BbcodeParser $parser,
        private ProfileRepository $profiles,
    ) {
    }

    public function store(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $thread = $this->threads->findById((int) ($request->params['thread'] ?? 0));
        if ($thread === null || $thread->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $board = $this->communityHelper->resolveBoard($community, (string) $thread->boardId);
        if ($board === null) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();
        if ($currentUser->isGuest()) {
            return Response::redirect('/login');
        }

        if ($thread->isLocked || $board->isLocked) {
            return new Response(
                status: 403,
                headers: ['Content-Type' => 'text/html; charset=utf-8'],
                body: 'Thread is locked.',
            );
        }

        $bodyText = trim((string) ($request->body['body'] ?? ''));
        if ($bodyText === '') {
            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id);
        }

        $profile = $currentUser->id !== null ? $this->profiles->findByUserId($currentUser->id) : null;
        $bodyParsed = $this->parser->parse($bodyText);
        $timestamp = time();
        $this->posts->create(
            communityId: $community->id,
            threadId: $thread->id,
            authorId: $currentUser->id ?? 0,
            bodyRaw: $bodyText,
            bodyParsed: $bodyParsed,
            signatureSnapshot: $profile?->signatureParsed,
            timestamp: $timestamp,
        );

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id);
    }

    private function notFound(Request $request): Response
    {
        return Response::notFound($this->view, $this->config, $this->auth, $request);
    }
}
