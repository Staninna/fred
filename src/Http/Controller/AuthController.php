<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class AuthController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig    $config,
        private AuthService  $auth,
        private CommunityHelper $communityHelper,
    ) {
    }

    public function showLoginForm(Request $request): Response
    {
        if ($this->auth->currentUser()->isAuthenticated()) {
            return Response::redirect('/');
        }

        return $this->renderLogin($request, []);
    }

    public function login(Request $request): Response
    {
        $username = trim((string) ($request->body['username'] ?? ''));
        $password = (string) ($request->body['password'] ?? '');

        $errors = [];

        if ($username === '') {
            $errors[] = 'Username is required.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        if ($errors !== []) {
            return $this->renderLogin($request, $errors, [
                'username' => $username,
            ], 422);
        }

        $success = $this->auth->login($username, $password);

        if (!$success) {
            return $this->renderLogin(
                $request,
                ['Invalid username or password.'],
                ['username' => $username],
                422
            );
        }

        return Response::redirect('/');
    }

    public function showRegisterForm(Request $request): Response
    {
        if ($this->auth->currentUser()->isAuthenticated()) {
            return Response::redirect('/');
        }

        return $this->renderRegister($request, []);
    }

    public function register(Request $request): Response
    {
        $username = trim((string) ($request->body['username'] ?? ''));
        $displayName = trim((string) ($request->body['display_name'] ?? ''));
        $password = (string) ($request->body['password'] ?? '');
        $passwordConfirmation = (string) ($request->body['password_confirmation'] ?? '');

        $errors = [];

        if ($username === '') {
            $errors[] = 'Username is required.';
        } elseif (\strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (\strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        if ($passwordConfirmation === '') {
            $errors[] = 'Please confirm your password.';
        } elseif ($password !== $passwordConfirmation) {
            $errors[] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            return $this->renderRegister($request, $errors, [
                'username' => $username,
                'display_name' => $displayName,
            ], 422);
        }

        try {
            $this->auth->register($username, $displayName, $password);
        } catch (\Throwable $exception) {
            return $this->renderRegister(
                $request,
                [$exception->getMessage()],
                [
                    'username' => $username,
                    'display_name' => $displayName,
                ],
                422
            );
        }

        return Response::redirect('/');
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();

        return Response::redirect('/');
    }

    private function renderLogin(Request $request, array $errors, array $old = [], int $status = 200): Response
    {
        $body = $this->view->render('pages/auth/login.php', [
            'pageTitle' => 'Sign in',
            'errors' => $errors,
            'old' => $old,
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'activePath' => $request->path,
            'navSections' => $this->communityHelper->navForCommunity(),
            'currentCommunity' => null,
            'customCss' => '',
        ]);

        return new Response(
            status: $status,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    private function renderRegister(Request $request, array $errors, array $old = [], int $status = 200): Response
    {
        $body = $this->view->render('pages/auth/register.php', [
            'pageTitle' => 'Create account',
            'errors' => $errors,
            'old' => $old,
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'activePath' => $request->path,
            'navSections' => $this->communityHelper->navForCommunity(),
            'currentCommunity' => null,
            'customCss' => '',
        ]);

        return new Response(
            status: $status,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }
}
