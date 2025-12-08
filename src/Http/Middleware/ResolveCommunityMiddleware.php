<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Http\Middleware\Concerns\HandlesNotFound;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ResolveCommunityMiddleware
{
    use HandlesNotFound;

    public function __construct(
        private CommunityContext $communityContext,
        private ViewRenderer $view,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $community = $this->communityContext->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $this->view->share('currentCommunity', $community);
        $this->view->share('navSections', $this->communityContext->navForCommunity($community));

        return $next($request->withAttribute('community', $community));
    }

    protected function view(): ViewRenderer
    {
        return $this->view;
    }
}
