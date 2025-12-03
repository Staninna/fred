<?php

declare(strict_types=1);

namespace Fred\Http\Routing;

use Fred\Http\Request;
use Fred\Http\Response;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $handler = $this->routes[$request->method][$request->path] ?? null;

        if (!is_callable($handler)) {
            return new Response(
                status: 404,
                headers: ['Content-Type' => 'text/html; charset=utf-8'],
                body: '<h1>Not Found</h1>',
            );
        }

        return $handler($request);
    }
}
