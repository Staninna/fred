<?php

declare(strict_types=1);

namespace Fred\Http\Routing;

use Fred\Http\Request;
use Fred\Http\Response;

use function file_get_contents;
use function filesize;
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
    /** @var array<string, array<string, array{handler: callable, middleware: array<int, callable>}>> */
    private array $staticRoutes = [];

    /** @var array<string, array<int, array{regex: string, paramNames: array<int, string>, handler: callable, middleware: array<int, callable>}>> */
    private array $dynamicRoutes = [];

    private readonly ?string $publicPath;

    /** @var null|callable */
    private $notFoundHandler = null;

    /** @var string[] */
    private array $groupPrefixStack = [];

    /** @var array<int, array<int, callable>> */
    private array $groupMiddlewareStack = [];

    public function __construct(?string $publicPath = null)
    {
        $this->publicPath = $publicPath === null ? null : rtrim($publicPath, '/\\');
    }

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $this->groupPrefixStack[] = $prefix;
        $this->groupMiddlewareStack[] = $middleware;

        $callback($this);

        array_pop($this->groupPrefixStack);
        array_pop($this->groupMiddlewareStack);
    }

    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $staticRoute = $this->staticRoutes[$request->method][$request->path] ?? null;

        if (\is_array($staticRoute)) {
            return $this->runMiddleware(
                $request,
                $staticRoute['handler'],
                $staticRoute['middleware'],
            );
        }

        $dynamicMatch = $this->matchDynamic($request);
        if ($dynamicMatch !== null) {
            return $this->runMiddleware(
                $dynamicMatch['request'],
                $dynamicMatch['handler'],
                $dynamicMatch['middleware'],
            );
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

    public function getRoutes(): array
    {
        return [
            'static' => $this->staticRoutes,
            'dynamic' => $this->dynamicRoutes,
        ];
    }

    private function addRoute(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $fullPath = $this->applyGroupPrefix($path);
        $combinedMiddleware = $this->gatherGroupMiddleware($middleware);

        if (str_contains($fullPath, '{')) {
            [$regex, $paramNames] = $this->compileRoute($fullPath);
            $this->dynamicRoutes[$method][] = [
                'path' => $fullPath,
                'regex' => $regex,
                'paramNames' => $paramNames,
                'handler' => $handler,
                'middleware' => $combinedMiddleware,
            ];

            return;
        }

        $this->staticRoutes[$method][$fullPath] = [
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => $combinedMiddleware,
        ];
    }

    /**
     * @return array{handler: callable, request: Request, middleware: array<int, callable>}|null
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
                'middleware' => $route['middleware'],
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

        $regex = rtrim($regex ?? '', '/');

        return ['#^' . $regex . '/?$#', $paramNames];
    }

    private function applyGroupPrefix(string $path): string
    {
        $prefix = '';

        foreach ($this->groupPrefixStack as $part) {
            $prefix .= '/' . ltrim($part, '/');
        }

        $normalizedPath = $path === '/' ? '' : '/' . ltrim($path, '/');
        $combined = ($prefix === '' ? '' : rtrim($prefix, '/')) . $normalizedPath;
        $combined = preg_replace('#//+#', '/', $combined) ?? '/';

        if ($combined === '') {
            return '/';
        }

        return $combined;
    }

    private function gatherGroupMiddleware(array $routeMiddleware): array
    {
        $merged = [];
        foreach ($this->groupMiddlewareStack as $stackMiddleware) {
            $merged = array_merge($merged, $stackMiddleware);
        }

        return array_merge($merged, $routeMiddleware);
    }

    /**
     * @param array<int, callable> $middleware
     */
    private function runMiddleware(Request $request, callable $handler, array $middleware): Response
    {
        $pipeline = array_reverse($middleware);
        $next = static fn (Request $incoming) => $handler($incoming);

        foreach ($pipeline as $layer) {
            $prevNext = $next;
            $next = static fn (Request $incoming) => $layer($incoming, $prevNext);
        }

        $result = $next($request);
        if (!$result instanceof Response) {
            throw new \RuntimeException('Middleware pipeline must return a Response.');
        }

        return $result;
    }

    private function tryServeStatic(Request $request): ?Response
    {
        if ($this->publicPath === null) {
            return null;
        }

        if (!\in_array($request->method, ['GET', 'HEAD'], true)) {
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
        $isHead = $request->method === 'HEAD';
        $body = $isHead ? '' : (string) file_get_contents($resolvedPath);

        return new Response(
            status: 200,
            headers: [
                'Content-Type' => $mimeType,
                'Content-Length' => $isHead ? '0' : (string) filesize($resolvedPath),
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
