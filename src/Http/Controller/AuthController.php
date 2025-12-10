<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;

use function strlen;

use Throwable;

final readonly class AuthController
{
    public function __construct(
        private ViewRenderer $view,
        private AuthService  $auth,
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
        } elseif (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 6) {
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
        } catch (Throwable $exception) {
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

    /**
     * @param string[] $errors
     * @param array<string, mixed> $old
     */
    private function renderLogin(Request $request, array $errors, array $old = [], int $status = 200): Response
    {
        $ctx = ViewContext::make()
            ->set('pageTitle', 'Sign in')
            ->set('errors', $errors)
            ->set('old', $old);

        return Response::view($this->view, 'pages/auth/login.php', $ctx, status: $status);
    }

    /**
     * @param string[] $errors
     * @param array<string, mixed> $old
     */
    private function renderRegister(Request $request, array $errors, array $old = [], int $status = 200): Response
    {
        $ctx = ViewContext::make()
            ->set('pageTitle', 'Create account')
            ->set('errors', $errors)
            ->set('old', $old);

        return Response::view($this->view, 'pages/auth/register.php', $ctx, status: $status);
    }
}
