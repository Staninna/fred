<?php

declare(strict_types=1);

use Fred\Application\Auth\AuthService;
use Fred\Application\Content\BbcodeParser;
use Fred\Http\Controller\AdminController;
use Fred\Http\Controller\AuthController;
use Fred\Http\Controller\BoardController;
use Fred\Http\Controller\CommunityController;
use Fred\Http\Controller\PostController;
use Fred\Http\Controller\ProfileController;
use Fred\Http\Controller\ModerationController;
use Fred\Http\Controller\SearchController;
use Fred\Http\Controller\UploadController;
use Fred\Http\Controller\ThreadController;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Http\Routing\Router;
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

use function http_build_query;
use function strtoupper;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
$env = DotenvLoader::load($basePath . '/.env');

$container = new Container();
$container->delegate(new ReflectionContainer(true));
$container->addShared('basePath', $basePath);
$container->addShared('env', $env);

$container->addShared(AppConfig::class, static fn () => ConfigLoader::fromArray($container->get('env'), $container->get('basePath')));
$container->addShared(PDO::class, static fn () => ConnectionFactory::make($container->get(AppConfig::class)));
$container->addShared(SqliteSessionHandler::class, static fn () => new SqliteSessionHandler($container->get(PDO::class)));
$container->addShared(FileLogger::class, static fn () => new FileLogger($container->get(AppConfig::class)->logsPath . '/app.log'));
$container->addShared(NullLogger::class, static fn () => new NullLogger());
$container->addShared(BbcodeParser::class, static fn () => new BbcodeParser());
$container->addShared(ViewRenderer::class, static fn () => new ViewRenderer($container->get('basePath') . '/resources/views'));
$container->addShared(Router::class, static fn () => new Router($container->get('basePath') . '/public'));

$sessionHandler = $container->get(SqliteSessionHandler::class);
session_set_save_handler($sessionHandler, true);
session_start();

$router = $container->get(Router::class);
$authService = $container->get(AuthService::class);
$communityController = $container->get(CommunityController::class);
$boardController = $container->get(BoardController::class);
$threadController = $container->get(ThreadController::class);
$postController = $container->get(PostController::class);
$adminController = $container->get(AdminController::class);
$moderationController = $container->get(ModerationController::class);
$authController = $container->get(AuthController::class);
$profileController = $container->get(ProfileController::class);
$searchController = $container->get(SearchController::class);
$uploadController = $container->get(UploadController::class);

$authRequired = static function (Request $request, callable $next) use ($authService): Response {
    if ($authService->currentUser()->isGuest()) {
        return Response::redirect('/login');
    }

    return $next($request);
};

$router->get('/', [$communityController, 'index']);
$router->post('/communities', [$communityController, 'store']);
$router->get('/login', [$authController, 'showLoginForm']);
$router->post('/login', [$authController, 'login']);
$router->get('/register', [$authController, 'showRegisterForm']);
$router->post('/register', [$authController, 'register']);
$router->post('/logout', [$authController, 'logout']);

$router->get('/c/{community}', [$communityController, 'show']);
$router->get('/uploads/{type}/{year}/{month}/{file}', [$uploadController, 'serve']);

$router->group('/c/{community}', function (Router $router) use (
    $communityController,
    $boardController,
    $threadController,
    $postController,
    $adminController,
    $profileController,
    $authRequired,
    $moderationController,
    $searchController
) {
    $router->get('/', [$communityController, 'show']);
    $router->get('/u/{username}', [$profileController, 'show']);

    $router->group('/settings', function (Router $router) use ($profileController) {
        $router->get('/profile', [$profileController, 'editProfile']);
        $router->post('/profile', [$profileController, 'updateProfile']);
        $router->get('/signature', [$profileController, 'editSignature']);
        $router->post('/signature', [$profileController, 'updateSignature']);
        $router->get('/avatar', [$profileController, 'editAvatar']);
        $router->post('/avatar', [$profileController, 'updateAvatar']);
    }, [$authRequired]);

    $router->get('/b/{board}', [$boardController, 'show']);
    $router->group('/b/{board}', function (Router $router) use ($threadController) {
        $router->get('/thread/new', [$threadController, 'create']);
        $router->post('/thread', [$threadController, 'store']);
    }, [$authRequired]);

    $router->get('/t/{thread}', [$threadController, 'show']);
    $router->post('/t/{thread}/reply', [$postController, 'store'], [$authRequired]);
    $router->post('/t/{thread}/lock', [$moderationController, 'lockThread'], [$authRequired]);
    $router->post('/t/{thread}/unlock', [$moderationController, 'unlockThread'], [$authRequired]);
    $router->post('/t/{thread}/sticky', [$moderationController, 'stickyThread'], [$authRequired]);
    $router->post('/t/{thread}/unsticky', [$moderationController, 'unstickyThread'], [$authRequired]);
    $router->post('/t/{thread}/announce', [$moderationController, 'announceThread'], [$authRequired]);
    $router->post('/t/{thread}/unannounce', [$moderationController, 'unannounceThread'], [$authRequired]);
    $router->post('/t/{thread}/move', [$moderationController, 'moveThread'], [$authRequired]);
    $router->get('/p/{post}/edit', [$moderationController, 'editPost'], [$authRequired]);
    $router->post('/p/{post}/delete', [$moderationController, 'deletePost'], [$authRequired]);
    $router->post('/p/{post}/edit', [$moderationController, 'editPost'], [$authRequired]);

    $router->get('/admin/bans', [$moderationController, 'listBans'], [$authRequired]);
    $router->post('/admin/bans', [$moderationController, 'createBan'], [$authRequired]);
    $router->post('/admin/bans/{ban}/delete', [$moderationController, 'deleteBan'], [$authRequired]);
    $router->get('/search', [$searchController, 'search']);

    $router->group('/admin', function (Router $router) use ($adminController) {
        $router->get('/structure', [$adminController, 'structure']);
        $router->post('/custom-css', [$adminController, 'updateCommunityCss']);
        $router->post('/categories', [$adminController, 'createCategory']);
        $router->post('/categories/{category}', [$adminController, 'updateCategory']);
        $router->post('/categories/{category}/delete', [$adminController, 'deleteCategory']);
        $router->post('/boards', [$adminController, 'createBoard']);
        $router->post('/boards/{board}', [$adminController, 'updateBoard']);
        $router->post('/boards/{board}/delete', [$adminController, 'deleteBoard']);
        $router->post('/moderators', [$adminController, 'addModerator']);
        $router->post('/moderators/{user}/delete', [$adminController, 'removeModerator']);
    }, [$authRequired]);
});

$router->setNotFoundHandler(function (Request $request) use ($container) {
    $view = $container->get(ViewRenderer::class);
    $auth = $container->get(AuthService::class);
    $config = $container->get(AppConfig::class);
    $communityHelper = $container->get(\Fred\Http\Controller\CommunityHelper::class);

    $body = $view->render('errors/404.php', [
        'pageTitle' => 'Page not found',
        'path' => $request->path,
        'activePath' => $request->path,
        'environment' => $config->environment,
        'currentUser' => $auth->currentUser(),
        'navSections' => $communityHelper->navForCommunity(),
    ]);

    return new Response(
        status: 404,
        headers: ['Content-Type' => 'text/html; charset=utf-8'],
        body: $body,
    );
});

$request = Request::fromGlobals();

$navResponse = trackNavigation($request);
if ($navResponse instanceof Response) {
    $navResponse->send();
    exit;
}

try {
    $response = $router->dispatch($request);
} catch (\Throwable $exception) {
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
            'environment' => $config->environment,
            'currentUser' => $auth->currentUser(),
            ...$debug,
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

function trackNavigation(Request $request): ?Response
{
    $method = strtoupper($request->method);
    $history = $_SESSION['nav_history'] ?? [];
    $index = $_SESSION['nav_index'] ?? (count($history) > 0 ? count($history) - 1 : -1);

    $history = is_array($history) ? $history : [];
    $index = is_int($index) ? $index : (count($history) > 0 ? count($history) - 1 : -1);

    if ($request->path === '/nav/back') {
        if ($index > 0) {
            $index--;
        }
        $_SESSION['nav_index'] = $index;
        $_SESSION['nav_skip_slice'] = true;
        $target = $history[$index] ?? '/';

        return Response::redirect($target);
    }

    if ($request->path === '/nav/forward') {
        if ($index < count($history) - 1) {
            $index++;
        }
        $_SESSION['nav_index'] = $index;
        $_SESSION['nav_skip_slice'] = true;
        $target = $history[$index] ?? '/';

        return Response::redirect($target);
    }

    if ($method !== 'GET' || !isTrackablePath($request->path)) {
        return null;
    }

    $skipSlice = (bool) ($_SESSION['nav_skip_slice'] ?? false);
    $_SESSION['nav_skip_slice'] = false;

    if (!$skipSlice && $index !== count($history) - 1) {
        $history = array_slice($history, 0, $index + 1);
        $index = count($history) - 1;
    }

    $fullPath = $request->path;
    if ($request->query !== []) {
        $fullPath .= '?' . http_build_query($request->query);
    }

    if ($index >= 0 && isset($history[$index]) && $history[$index] === $fullPath) {
        // already at this position; keep index as-is
    } elseif ($history === [] || $history[count($history) - 1] !== $fullPath) {
        $history[] = $fullPath;
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }
        $index = count($history) - 1;
    }

    $_SESSION['nav_history'] = $history;
    $_SESSION['nav_index'] = $index;

    return null;
}

function isTrackablePath(string $path): bool
{
    if ($path === '/nav/back' || $path === '/nav/forward') {
        return false;
    }

    // Skip static assets and uploads
    if (preg_match('#\\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|txt)$#i', $path)) {
        return false;
    }
    if (str_starts_with($path, '/uploads/')) {
        return false;
    }

    return true;
}
