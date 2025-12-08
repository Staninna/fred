<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Application\Auth\CurrentUser;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class EnrichViewContextMiddleware
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private CommunityContext $communityContext,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        /** @var CurrentUser|null $currentUser */
        $currentUser = $request->attribute('currentUser');

        $this->view->share('currentUser', $currentUser);
        $this->view->share('environment', $this->config->environment);
        $this->view->share('baseUrl', $this->config->baseUrl);
        $this->view->share('activePath', $request->path);
        
        // Default values
        $this->view->share('currentCommunity', null);
        $this->view->share('customCss', '');
        $this->view->share('navSections', $this->communityContext->navForCommunity(null));

        return $next($request);
    }
}
