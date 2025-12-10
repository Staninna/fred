<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Http\Middleware\Concerns\HandlesNotFound;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ResolveCommunityMiddleware
{
    use HandlesNotFound;

    public function __construct(
        private CommunityContext $communityContext,
        private ViewRenderer $view,
        private AppConfig $config,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $community = $this->communityContext->resolveCommunity($request->params['community'] ?? null);

        if ($community === null) {
            return $this->notFound($request, 'Community not found: ' . ($request->params['community'] ?? 'null'));
        }

        $this->view->share('currentCommunity', $community);
        $this->view->share('navSections', $this->communityContext->navForCommunity($community));
        $context = $request->context()->withCommunity($community);

        return $next(
            $request
                ->withContext($context)
                ->withAttribute('community', $community),
        );
    }

    protected function view(): ViewRenderer
    {
        return $this->view;
    }

    protected function config(): AppConfig
    {
        return $this->config;
    }
}
