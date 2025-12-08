<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Application\Auth\AuthService;
use Fred\Http\Request;
use Fred\Http\Response;

final readonly class RequireAuthMiddleware
{
    public function __construct(private AuthService $auth)
    {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        if ($this->auth->currentUser()->isGuest()) {
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
