<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class HomeController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private CommunityHelper $communityHelper,
    ) {
    }

    public function index(Request $request): Response
    {
        $navSections = $this->communityHelper->navForCommunity();

        $ctx = ViewContext::make()
            ->set('pageTitle', 'Fred Forum Engine')
            ->set('activePath', $request->path)
            ->set('navSections', $navSections)
            ->set('environment', $this->config->environment)
            ->set('baseUrl', $this->config->baseUrl)
            ->set('currentUser', $this->auth->currentUser());

        return Response::view($this->view, 'pages/home.php', $ctx);
    }
}
