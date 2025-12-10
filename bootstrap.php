<?php

declare(strict_types=1);

use Fred\Application\Auth\AuthService;
use Fred\Application\Content\BbcodeParser;
use Fred\Application\Content\LinkPreviewer;
use Fred\Application\Security\CsrfGuard;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Http\Routing\Router;
use Fred\Http\Navigation\NavigationTracker;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Config\ConfigLoader;
use Fred\Infrastructure\Database\ConnectionFactory;
use Fred\Infrastructure\Env\DotenvLoader;
use Fred\Infrastructure\Logging\FileLogger;
use Fred\Infrastructure\Logging\NullLogger;
use Fred\Infrastructure\Session\SqliteSessionHandler;
use Fred\Infrastructure\View\ViewRenderer;
use League\Container\Container;
use League\Container\ReflectionContainer;
use PDO;
use Throwable;

require __DIR__ . '/vendor/autoload.php';

return (static function (): array {
    $basePath = __DIR__;
    $env = DotenvLoader::load($basePath . '/.env');

    $container = new Container();
    $container->delegate(new ReflectionContainer(true));
    $container->addShared('basePath', $basePath);
    $container->addShared('env', $env);

    $container->addShared(AppConfig::class, static fn () => ConfigLoader::fromArray($container->get('env'), $container->get('basePath')));
    $container->addShared(CsrfGuard::class, static fn () => new CsrfGuard());
    $container->addShared(PDO::class, static fn () => ConnectionFactory::make($container->get(AppConfig::class)));
    $container->addShared(SqliteSessionHandler::class, static fn () => new SqliteSessionHandler($container->get(PDO::class)));
    $container->addShared(FileLogger::class, static fn () => new FileLogger($container->get(AppConfig::class)->logsPath . '/app.log'));
    $container->addShared(NullLogger::class, static fn () => new NullLogger());
    $container->addShared(BbcodeParser::class, static fn () => new BbcodeParser());
    $container->addShared(LinkPreviewer::class, static fn () => new LinkPreviewer($container->get(AppConfig::class)));
    $container->addShared(ViewRenderer::class, static fn () => new ViewRenderer(
        viewPath: $container->get('basePath') . '/resources/views',
        sharedData: [
            'csrfToken' => $container->get(CsrfGuard::class)->token(),
        ],
    ));
    $container->addShared(NavigationTracker::class, static fn () => new NavigationTracker());
    $container->addShared(Router::class, static fn () => new Router($container->get('basePath') . '/public'));

    $config = $container->get(AppConfig::class);
    $cookieSecure = str_starts_with($config->baseUrl, 'https://');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $cookieSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $cookieSecure ? '1' : '0');

    $sessionHandler = $container->get(SqliteSessionHandler::class);
    session_set_save_handler($sessionHandler, true);
    session_start();

    $router = $container->get(Router::class);
    $csrf = $container->get(CsrfGuard::class);
    $navigationTracker = $container->get(NavigationTracker::class);

    $dispatch = static function () use ($container, $router, $csrf, $navigationTracker): void {
        $request = Request::fromGlobals();

        if ($request->method === 'POST' && !$csrf->isValid($request)) {
            try {
                $view = $container->get(ViewRenderer::class);
                $config = $container->get(AppConfig::class);
                $auth = $container->get(AuthService::class);

                $body = $view->render('errors/419.php', [
                    'pageTitle' => 'CSRF token mismatch',
                ]);
            } catch (Throwable) {
                $body = '<h1>CSRF token mismatch</h1>';
            }

            (new Response(
                status: 419,
                headers: ['Content-Type' => 'text/html; charset=utf-8'],
                body: $body,
            ))->send();

            return;
        }

        $navResponse = $navigationTracker->track($request, $_SESSION);

        if ($navResponse instanceof Response) {
            $navResponse->send();

            return;
        }

        try {
            $response = $router->dispatch($request);
        } catch (Throwable $exception) {
            $logger = $container->get(FileLogger::class);
            $logger->error($exception->getMessage(), ['exception' => $exception]);

            try {
                $view = $container->get(ViewRenderer::class);
                $config = $container->get(AppConfig::class);
                $auth = $container->get(AuthService::class);

                $debug = [];

                if ($config->environment !== 'production') {
                    $debug = [
                        'errorMessage' => $exception->getMessage(),
                        'errorTrace' => $exception->getTraceAsString(),
                    ];
                }

                $body = $view->render('errors/500.php', [
                    'pageTitle' => 'Server error',
                    ...$debug,
                ]);
            } catch (Throwable) {
                $body = '<h1>Server Error</h1>';
            }

            $response = new Response(
                status: 500,
                headers: ['Content-Type' => 'text/html; charset=utf-8'],
                body: $body,
            );
        }

        $response->send();
    };

    return [$container, $router, $dispatch];
})();
