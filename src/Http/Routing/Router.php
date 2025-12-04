<?php

declare(strict_types=1);

namespace Fred\Http\Routing;

use Fred\Http\Request;
use Fred\Http\Response;

use function file_get_contents;
use function filesize;
use function in_array;
use function is_file;
use function preg_match;
use function preg_replace_callback;
use function pathinfo;
use function realpath;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function strtolower;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_EXTENSION;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $staticRoutes = [];

    /** @var array<string, array<int, array{regex: string, paramNames: array<int, string>, handler: callable}>> */
    private array $dynamicRoutes = [];

    private readonly ?string $publicPath;

    /** @var null|callable */
    private $notFoundHandler = null;

    public function __construct(?string $publicPath = null)
    {
        $this->publicPath = $publicPath === null ? null : rtrim($publicPath, '/\\');
    }

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $staticHandler = $this->staticRoutes[$request->method][$request->path] ?? null;

        if (\is_callable($staticHandler)) {
            return $staticHandler($request);
        }

        $dynamicMatch = $this->matchDynamic($request);
        if ($dynamicMatch !== null) {
            return $dynamicMatch['handler']($dynamicMatch['request']);
        }

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

    private function addRoute(string $method, string $path, callable $handler): void
    {
        if (str_contains($path, '{')) {
            [$regex, $paramNames] = $this->compileRoute($path);
            $this->dynamicRoutes[$method][] = [
                'regex' => $regex,
                'paramNames' => $paramNames,
                'handler' => $handler,
            ];

            return;
        }

        $this->staticRoutes[$method][$path] = $handler;
    }

    /**
     * @return array{handler: callable, request: Request}|null
     */
    private function matchDynamic(Request $request): ?array
    {
        foreach ($this->dynamicRoutes[$request->method] ?? [] as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($route['paramNames'] as $index => $name) {
                $params[$name] = $matches[$index + 1] ?? null;
            }

            $requestWithParams = $request->withParams($params);

            return [
                'handler' => $route['handler'],
                'request' => $requestWithParams,
            ];
        }

        return null;
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function compileRoute(string $path): array
    {
        $paramNames = [];
        $regex = preg_replace_callback(
            '/\{([^}]+)\}/',
            static function (array $matches) use (&$paramNames): string {
                $paramNames[] = $matches[1];

                return '([^/]+)';
            },
            $path,
        );

        return ['#^' . $regex . '$#', $paramNames];
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
