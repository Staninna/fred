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
}
