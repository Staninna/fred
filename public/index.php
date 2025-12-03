<?php

declare(strict_types=1);

use Fred\Http\Controller\HealthController;
use Fred\Http\Controller\HomeController;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Http\Routing\Router;
use Fred\Infrastructure\Config\ConfigLoader;
use Fred\Infrastructure\Database\ConnectionFactory;
use Fred\Infrastructure\Env\DotenvLoader;
use Fred\Infrastructure\Session\SqliteSessionHandler;
use Fred\Infrastructure\View\ViewRenderer;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
$env = DotenvLoader::load($basePath . '/.env');
$config = ConfigLoader::fromArray($env, $basePath);
$pdo = ConnectionFactory::make($config);

$sessionHandler = new SqliteSessionHandler($pdo);
session_set_save_handler($sessionHandler, true);
session_start();

$view = new ViewRenderer($basePath . '/resources/views');
$router = new Router();
$router->get('/', [new HomeController($view, $config), 'index']);
$router->get('/health', [new HealthController($view, $config), 'show']);
$router->setNotFoundHandler(function (Request $request) use ($view) {
    $body = $view->render('errors/404.php', [
        'pageTitle' => 'Page not found',
        'path' => $request->path,
        'activePath' => $request->path,
    ]);

    return new Response(
        status: 404,
        headers: ['Content-Type' => 'text/html; charset=utf-8'],
        body: $body,
    );
});

$request = Request::fromGlobals();

try {
    $response = $router->dispatch($request);
} catch (\Throwable $exception) {
    try {
        $body = $view->render('errors/500.php', [
            'pageTitle' => 'Server error',
        ]);
    } catch (\Throwable) {
        $body = '<h1>Server Error</h1>';
    }

    $response = new Response(
        status: 500,
        headers: ['Content-Type' => 'text/html; charset=utf-8'],
        body: $body,
    );
}

$response->send();
