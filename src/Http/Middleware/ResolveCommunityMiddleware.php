<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Http\Controller\CommunityHelper;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ResolveCommunityMiddleware
{
    public function __construct(
        private CommunityHelper $communityHelper,
        private ViewRenderer $view,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return Response::notFound(
                view: $this->view,
                request: $request,
            );
        }

        return $next($request->withAttribute('community', $community));
    }
}
