<?php

declare(strict_types=1);

namespace Fred\Http\Routing;

use Fred\Http\Request;
use Fred\Http\Response;
use function file_get_contents;
use function filesize;
use function in_array;
use function is_file;
use function pathinfo;
use function realpath;
use function rtrim;
use function str_starts_with;
use function strtolower;
use const DIRECTORY_SEPARATOR;
use const PATHINFO_EXTENSION;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    private readonly ?string $publicPath;

    private $notFoundHandler = null;

    public function __construct(?string $publicPath = null)
    {
        $this->publicPath = $publicPath === null ? null : rtrim($publicPath, '/\\');
    }

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $methodRoutes = $this->routes[$request->method] ?? [];
        $handler = $methodRoutes[$request->path] ?? null;

        if (!is_callable($handler)) {
            $staticResponse = $this->tryServeStatic($request);
            if ($staticResponse instanceof Response) {
                return $staticResponse;
            }

            if ($this->notFoundHandler !== null) {
                return ($this->notFoundHandler)($request);
            }

            return new Response(
                status: 404,
                headers: ['Content-Type' => 'text/html; charset=utf-8'],
                body: '<h1>Not Found</h1>',
            );
        }

        return $handler($request);
    }

    private function tryServeStatic(Request $request): ?Response
    {
        if ($this->publicPath === null) {
            return null;
        }

        if (!in_array($request->method, ['GET', 'HEAD'], true)) {
            return null;
        }

        $resolvedPath = realpath($this->publicPath . $request->path);

        if ($resolvedPath === false || !is_file($resolvedPath)) {
            return null;
        }

        $rootWithSeparator = $this->publicPath . DIRECTORY_SEPARATOR;

        if (!str_starts_with($resolvedPath, $rootWithSeparator)) {
            return null;
        }

        $mimeType = $this->guessMimeType($resolvedPath);
        $body = $request->method === 'HEAD' ? '' : (string) file_get_contents($resolvedPath);

        return new Response(
            status: 200,
            headers: [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) filesize($resolvedPath),
            ],
            body: $body,
        );
    }

    private function guessMimeType(string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'txt' => 'text/plain; charset=utf-8',
            default => 'application/octet-stream',
        };
    }
}
