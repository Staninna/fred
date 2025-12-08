<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Application\Auth\AuthService;
use Fred\Http\Request;
use Fred\Http\Response;

final readonly class InjectCurrentUserMiddleware
{
    public function __construct(private AuthService $auth)
    {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $currentUser = $this->auth->currentUser();
        $request = $request->withAttribute('currentUser', $currentUser);

        return $next($request);
    }
}
