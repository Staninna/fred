<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Thread;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewRenderer;

abstract readonly class Controller
{
    public function __construct(
        protected ViewRenderer $view,
        protected AppConfig $config,
        protected AuthService $auth,
        protected CommunityContext $communityContext,
    ) {
    }

    protected function notFound(Request $request, ?string $context = null): Response
    {
        return Response::notFound(
            view: $this->view,
            config: $this->config,
            auth: $this->auth,
            request: $request,
            context: $context,
        );
    }

    protected function forbidden(): Response
    {
        return Response::forbidden();
    }

    protected function redirectToThread(Community $community, Thread $thread, ?int $page = null, ?string $anchor = null): Response
    {
        $url = '/c/' . $community->slug . '/t/' . $thread->id;

        if ($page !== null) {
            $url .= '?page=' . $page;
        }

        if ($anchor !== null) {
            $url .= ($page !== null ? '#' : '?#') . $anchor;
        }

        return Response::redirect($url);
    }

    protected function redirectBack(Request $request, string $fallback = '/'): Response
    {
        $referer = $request->header('referer');

        return Response::redirect($referer ?? $fallback);
    }
}
