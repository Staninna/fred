<?php

declare(strict_types=1);

namespace Fred\Http\Routing;

use const DIRECTORY_SEPARATOR;

use function file_get_contents;
use function filesize;

use Fred\Http\Request;
use Fred\Http\Response;

use function in_array;
use function is_array;
use function is_file;
use function is_callable;
use function pathinfo;

use const PATHINFO_EXTENSION;

use function preg_match;
use function preg_replace_callback;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function realpath;
use function rtrim;

use RuntimeException;
use Closure;

use function str_contains;
use function str_starts_with;
use function strtolower;

final class Router
{
    /** @var array<string, array<string, array{handler: callable, middleware: array<int, callable|string>}>> */
    private array $staticRoutes = [];

    /** @var array<string, array<int, array{regex: string, paramNames: array<int, string>, handler: callable, middleware: array<int, callable|string>}>> */
    private array $dynamicRoutes = [];

    private readonly ?string $publicPath;

    /** @var null|callable */
    private $notFoundHandler = null;

    /** @var string[] */
    private array $groupPrefixStack = [];

    /** @var array<int, array<int, callable|string>> */
    private array $groupMiddlewareStack = [];

    /** @var array<int, callable|string> */
    private array $globalMiddleware = [];

    /** @var null|callable(string):callable */
    private readonly ?Closure $middlewareResolver;

    private readonly LoggerInterface $logger;

    public function __construct(?string $publicPath = null, ?callable $middlewareResolver = null, ?LoggerInterface $logger = null)
    {
        $this->publicPath = $publicPath === null ? null : rtrim($publicPath, '/\\');
        $this->middlewareResolver = $middlewareResolver !== null ? Closure::fromCallable($middlewareResolver) : null;
        $this->logger = $logger ?? new NullLogger();
    }

    public function addGlobalMiddleware(callable|string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * @param array<int, callable|string> $middleware
     */
    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * @param array<int, callable|string> $middleware
     */
    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * @param array<int, callable|string> $middleware
     */
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

        if (is_array($staticRoute)) {
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

        $this->logger->warning('Route not found', [
            'method' => $request->method,
            'path' => $request->path,
            'query' => $request->query,
        ]);

        if ($this->notFoundHandler !== null) {
            $response = ($this->notFoundHandler)($request);
            
            if (!$response instanceof Response) {
                throw new RuntimeException('Not found handler must return a Response.');
            }
            
            return $response;
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

    /**
     * Dump route map for diagnostics.
     * Returns a human-readable list of all registered routes.
     * 
     * @return string
     */
    public function dumpRouteMap(): string
    {
        $lines = [];
        $routes = [];
        // Dynamically determine all registered HTTP methods
        $methods = array_unique(array_merge(
            array_keys($this->staticRoutes),
            array_keys($this->dynamicRoutes)
        ));
        sort($methods);

        // Gather all routes for pretty printing
        foreach ($methods as $method) {
            foreach ($this->staticRoutes[$method] ?? [] as $path => $route) {
                $middlewareCount = count($route['middleware']);
                $middlewareInfo = $middlewareCount > 0 ? " [{$middlewareCount} middleware]" : '';
                $routes[] = [
                    'method' => $method,
                    'path' => $path,
                    'handler' => $this->stringifyHandler($route['handler']),
                    'middlewareInfo' => $middlewareInfo,
                ];
            }
            foreach ($this->dynamicRoutes[$method] ?? [] as $route) {
                $middlewareCount = count($route['middleware']);
                $middlewareInfo = $middlewareCount > 0 ? " [{$middlewareCount} middleware]" : '';
                $routes[] = [
                    'method' => $method,
                    'path' => $route['path'],
                    'handler' => $this->stringifyHandler($route['handler']),
                    'middlewareInfo' => $middlewareInfo,
                ];
            }
        }

        // Calculate max widths
        $methodWidth = 6;
        $pathWidth = 4;
        $handlerWidth = 7;
        foreach ($routes as $r) {
            $methodWidth = max($methodWidth, strlen($r['method']));
            $pathWidth = max($pathWidth, strlen($r['path']) + strlen($r['middlewareInfo']));
            $handlerWidth = max($handlerWidth, strlen($r['handler']));
        }

        // ANSI color codes
        $colorMethod = "\033[1;34m"; // bold blue
        $colorHandler = "\033[0;32m"; // green
        $colorReset = "\033[0m";

        $lines[] = '';
        $lines[] = '┌' . str_repeat('─', $methodWidth+2) . '┬' . str_repeat('─', $pathWidth+2) . '┬' . str_repeat('─', $handlerWidth+2) . '┐';
        $lines[] = sprintf(
            "│ %s%-{$methodWidth}s%s │ %-{$pathWidth}s │ %s%-{$handlerWidth}s%s │",
            $colorMethod, 'METHOD', $colorReset,
            'PATH',
            $colorHandler, 'HANDLER', $colorReset
        );
        $lines[] = '├' . str_repeat('─', $methodWidth+2) . '┼' . str_repeat('─', $pathWidth+2) . '┼' . str_repeat('─', $handlerWidth+2) . '┤';

        foreach ($routes as $r) {
            $pathDisplay = $r['path'] . $r['middlewareInfo'];
            $lines[] = sprintf(
                "│ %s%-{$methodWidth}s%s │ %-{$pathWidth}s │ %s%-{$handlerWidth}s%s │",
                $colorMethod, $r['method'], $colorReset,
                $pathDisplay,
                $colorHandler, $r['handler'], $colorReset
            );
        }
        $lines[] = '└' . str_repeat('─', $methodWidth+2) . '┴' . str_repeat('─', $pathWidth+2) . '┴' . str_repeat('─', $handlerWidth+2) . '┘';
        $lines[] = '';
        $lines[] = sprintf('Total: %d static, %d dynamic routes',
            count($this->staticRoutes['GET'] ?? []) + count($this->staticRoutes['POST'] ?? []),
            count($this->dynamicRoutes['GET'] ?? []) + count($this->dynamicRoutes['POST'] ?? [])
        );

        return implode("\n", $lines);
    }

    /**
     * Convert a handler to a string for display.
     */
    private function stringifyHandler($handler): string
    {
        if (is_array($handler)) {
            if (is_object($handler[0])) {
                $class = get_class($handler[0]);
            } else {
                $class = (string) $handler[0];
            }
            return $class . '::' . $handler[1];
        }
        if (is_string($handler)) {
            return $handler;
        }
        if ($handler instanceof \Closure) {
            return 'Closure';
        }
        if (is_object($handler)) {
            return get_class($handler);
        }
        return 'Unknown';
    }

    /**
     * @param array<int, callable|string> $middleware
     */
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

    /**
     * @param array<int, callable|string> $routeMiddleware
     * @return array<int, callable|string>
     */
    private function gatherGroupMiddleware(array $routeMiddleware): array
    {
        $merged = [];

        foreach ($this->groupMiddlewareStack as $stackMiddleware) {
            $merged = array_merge($merged, $stackMiddleware);
        }

        return array_merge($merged, $routeMiddleware);
    }

    /**
      * @param array<int, callable|string> $middleware
     */
    private function runMiddleware(Request $request, callable $handler, array $middleware): Response
    {
          $allMiddleware = array_merge($this->globalMiddleware, $middleware);
          $resolvedMiddleware = array_map(fn ($mw) => $this->resolveMiddleware($mw), $allMiddleware);
          $pipeline = array_reverse($resolvedMiddleware);
        $next = static fn (Request $incoming) => $handler($incoming);

        foreach ($pipeline as $layer) {
            $prevNext = $next;
            $next = static fn (Request $incoming) => $layer($incoming, $prevNext);
        }

        $result = $next($request);

        if (!$result instanceof Response) {
            $type = get_debug_type($result);
            $this->logger->error('Handler/middleware returned non-Response', [
                'type' => $type,
                'method' => $request->method,
                'path' => $request->path,
            ]);
            throw new RuntimeException(sprintf(
                'Handler or middleware must return Response, got %s for %s %s',
                $type,
                $request->method,
                $request->path
            ));
        }

        return $result;
    }

    private function resolveMiddleware(callable|string $middleware): callable
    {
        if (is_callable($middleware)) {
            return $middleware;
        }

        if ($this->middlewareResolver !== null) {
            $resolved = ($this->middlewareResolver)($middleware);

            if (!is_callable($resolved)) {
                throw new RuntimeException('Middleware resolver must return a callable for alias: ' . $middleware);
            }

            return $resolved;
        }

        throw new RuntimeException('No middleware resolver configured for alias: ' . $middleware);
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
