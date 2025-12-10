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
    ) {
    }

    public function index(Request $request): Response
    {
        $ctx = ViewContext::make()
            ->set('pageTitle', 'Fred Forum Engine');

        return Response::view($this->view, 'pages/home.php', $ctx);
    }
}
