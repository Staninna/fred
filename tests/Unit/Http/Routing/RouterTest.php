<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Routing;

use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Http\Routing\Router;
use Tests\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchesStaticRoute(): void
    {
        $router = new Router();
        $router->get('/hello', static fn (Request $request) => new Response(
            status: 200,
            headers: ['Content-Type' => 'text/plain'],
            body: 'hi',
        ));

        $response = $router->dispatch(new Request(
            method: 'GET',
            path: '/hello',
            query: [],
            body: [],
        ));

        $this->assertSame(200, $response->status);
        $this->assertSame('hi', $response->body);
    }

    public function testDispatchesDynamicRoute(): void
    {
        $router = new Router();
        $router->get('/c/{slug}', static fn (Request $request) => new Response(
            status: 200,
            headers: ['Content-Type' => 'text/plain'],
            body: $request->params['slug'] ?? '',
        ));

        $response = $router->dispatch(new Request(
            method: 'GET',
            path: '/c/fred',
            query: [],
            body: [],
        ));

        $this->assertSame(200, $response->status);
        $this->assertSame('fred', $response->body);
    }

    public function testServesStaticFileWhenPathExists(): void
    {
        $tempRoot = $this->createTempDir('fred-router-');
        $publicDir = $tempRoot . '/public';
        mkdir($publicDir, 0777, true);

        $filePath = $publicDir . '/hello.txt';
        file_put_contents($filePath, 'hi');

        $router = new Router($publicDir);

        $response = $router->dispatch(new Request(
            method: 'GET',
            path: '/hello.txt',
            query: [],
            body: [],
        ));

        $this->assertSame(200, $response->status);
        $this->assertSame('hi', $response->body);
        $this->assertSame('text/plain; charset=utf-8', $response->headers['Content-Type']);

        $this->removeDirectory($tempRoot);
    }

    public function testRouteGroupPrefixesAreApplied(): void
    {
        $router = new Router();

        $router->group('/c/{community}', function (Router $router) {
            $router->get('/b/{board}', static fn (Request $request) => new Response(
                status: 200,
                headers: ['Content-Type' => 'text/plain'],
                body: ($request->params['community'] ?? '') . '/' . ($request->params['board'] ?? ''),
            ));
        });

        $response = $router->dispatch(new Request(
            method: 'GET',
            path: '/c/main/b/general',
            query: [],
            body: [],
        ));

        $this->assertSame(200, $response->status);
        $this->assertSame('main/general', $response->body);
    }

    public function testMiddlewareRunsInOrder(): void
    {
        $calls = [];

        $router = new Router();
        $router->get('/hello', static function (Request $request) {
            return new Response(
                status: 200,
                headers: ['Content-Type' => 'text/plain'],
                body: 'handler',
            );
        }, [
            static function (Request $request, callable $next) use (&$calls): Response {
                $calls[] = 'first';

                return $next($request);
            },
            static function (Request $request, callable $next) use (&$calls): Response {
                $calls[] = 'second';

                return $next($request);
            },
        ]);

        $response = $router->dispatch(new Request(
            method: 'GET',
            path: '/hello',
            query: [],
            body: [],
        ));

        $this->assertSame(200, $response->status);
        $this->assertSame(['first', 'second'], $calls);
        $this->assertSame('handler', $response->body);
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $router = new Router();
        $router->get('/blocked', static fn () => new Response(
            status: 200,
            headers: ['Content-Type' => 'text/plain'],
            body: 'should not run',
        ), [
            static fn () => new Response(
                status: 403,
                headers: ['Content-Type' => 'text/plain'],
                body: 'blocked',
            ),
        ]);

        $response = $router->dispatch(new Request(
            method: 'GET',
            path: '/blocked',
            query: [],
            body: [],
        ));

        $this->assertSame(403, $response->status);
        $this->assertSame('blocked', $response->body);
    }

    public function testDumpRouteMap(): void
    {
        $router = new Router();
        $router->get('/', static fn () => new Response(200, [], 'home'));
        $router->get('/hello', static fn () => new Response(200, [], 'hello'), [
            static fn (Request $r, callable $n) => $n($r),
        ]);
        $router->post('/submit', static fn () => new Response(200, [], 'ok'));
        $router->get('/c/{community}/b/{board}', static fn () => new Response(200, [], 'board'));

        $map = $router->dumpRouteMap();

        $this->assertStringContainsString('Registered Routes:', $map);
        $this->assertStringContainsString('GET    /', $map);
        $this->assertStringContainsString('GET    /hello [1 middleware]', $map);
        $this->assertStringContainsString('POST   /submit', $map);
        $this->assertStringContainsString('GET    /c/{community}/b/{board}', $map);
        $this->assertStringContainsString('Total:', $map);
    }
}
