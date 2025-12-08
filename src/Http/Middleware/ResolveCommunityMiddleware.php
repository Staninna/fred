<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Http\Middleware\Concerns\HandlesNotFound;
use Fred\Http\Controller\CommunityHelper;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ResolveCommunityMiddleware
{
    use HandlesNotFound;

    public function __construct(
        private CommunityHelper $communityHelper,
        private ViewRenderer $view,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        return $next($request->withAttribute('community', $community));
    }

    protected function view(): ViewRenderer
    {
        return $this->view;
    }
}
