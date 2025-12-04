<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class HomeController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig    $config,
        private AuthService  $auth,
    ) {
    }

    public function index(Request $request): Response
    {
        $navSections = [
            [
                'title' => 'Communities',
                'items' => [
                    ['label' => 'Main Plaza', 'href' => '#'],
                    ['label' => 'Retro PC', 'href' => '#'],
                    ['label' => 'Design Lab', 'href' => '#'],
                ],
            ],
            [
                'title' => 'Boards',
                'items' => [
                    ['label' => 'Announcements', 'href' => '#'],
                    ['label' => 'General Chat', 'href' => '#'],
                    ['label' => 'Help Desk', 'href' => '#'],
                ],
            ],
        ];

        $body = $this->view->render('pages/home.php', [
            'pageTitle' => 'Fred Forum Engine',
            'activePath' => $request->path,
            'navSections' => $navSections,
            'environment' => $this->config->environment,
            'baseUrl' => $this->config->baseUrl,
            'currentUser' => $this->auth->currentUser(),
        ]);

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }
}
