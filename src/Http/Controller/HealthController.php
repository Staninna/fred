<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewRenderer;

final class HealthController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly AppConfig $config,
        private readonly AuthService $auth,
    ) {
    }

    public function show(Request $request): Response
    {
        $body = $this->view->render('pages/health.php', [
            'pageTitle' => 'Health Check',
            'environment' => $this->config->environment,
            'baseUrl' => $this->config->baseUrl,
            'sessionId' => session_id(),
            'activePath' => $request->path,
            'currentUser' => $this->auth->currentUser(),
        ]);

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }
}
