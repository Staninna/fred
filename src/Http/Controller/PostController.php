<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Content\BbcodeParser;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ThreadRepository;

use function trim;

final readonly class PostController
{
    public function __construct(
        private AuthService $auth,
        private CommunityRepository $communities,
        private BoardRepository $boards,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private BbcodeParser $parser,
        private ProfileRepository $profiles,
    ) {
    }

    public function store(Request $request): Response
    {
        $community = $this->communities->findBySlug((string) ($request->params['community'] ?? ''));
        if ($community === null) {
            return $this->notFound();
        }

        $thread = $this->threads->findById((int) ($request->params['thread'] ?? 0));
        if ($thread === null || $thread->communityId !== $community->id) {
            return $this->notFound();
        }

        $board = $this->boards->findById($thread->boardId);
        if ($board === null || $board->communityId !== $community->id) {
            return $this->notFound();
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

    private function notFound(): Response
    {
        return new Response(
            status: 404,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: '<h1>Not Found</h1>',
        );
    }
}
