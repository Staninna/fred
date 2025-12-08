<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
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

    protected function notFound(Request $request): Response
    {
        return Response::notFound(
            view: $this->view,
            config: $this->config,
            auth: $this->auth,
            request: $request,
        );
    }
}
