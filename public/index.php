<?php

declare(strict_types=1);

use Fred\Application\Auth\AuthService;
use Fred\Application\Content\BbcodeParser;
use Fred\Application\Content\LinkPreviewer;
use Fred\Http\Controller\AdminController;
use Fred\Http\Controller\AuthController;
use Fred\Http\Controller\BoardController;
use Fred\Http\Controller\CommunityController;
use Fred\Http\Controller\PostController;
use Fred\Http\Controller\ProfileController;
use Fred\Http\Controller\ModerationController;
use Fred\Http\Controller\ReactionController;
use Fred\Http\Controller\MentionController;
use Fred\Http\Controller\SearchController;
use Fred\Http\Controller\UploadController;
use Fred\Http\Controller\ThreadController;
use Fred\Http\Navigation\NavigationTracker;
use Fred\Http\Middleware\EnrichViewContextMiddleware;
use Fred\Http\Middleware\InjectCurrentUserMiddleware;
use Fred\Http\Middleware\PermissionMiddleware;
use Fred\Http\Middleware\ValidateResourceAttributesMiddleware;
use Fred\Http\Middleware\RequireAuthMiddleware;
use Fred\Http\Middleware\ResolveBoardMiddleware;
use Fred\Http\Middleware\ResolveCommunityMiddleware;
use Fred\Http\Middleware\ResolvePostMiddleware;
use Fred\Http\Middleware\ResolveThreadMiddleware;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Http\Routing\Router;
use Fred\Application\Security\CsrfGuard;
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

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
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
$navigationTracker = $container->get(NavigationTracker::class);
$authService = $container->get(AuthService::class);
$communityController = $container->get(CommunityController::class);
$boardController = $container->get(BoardController::class);
$threadController = $container->get(ThreadController::class);
$postController = $container->get(PostController::class);
$adminController = $container->get(AdminController::class);
$moderationController = $container->get(ModerationController::class);
$reactionController = $container->get(ReactionController::class);
$mentionController = $container->get(MentionController::class);
$authController = $container->get(AuthController::class);
$profileController = $container->get(ProfileController::class);
$searchController = $container->get(SearchController::class);
$uploadController = $container->get(UploadController::class);
$authRequired = $container->get(RequireAuthMiddleware::class);
$injectCurrentUser = $container->get(InjectCurrentUserMiddleware::class);
$enrichViewContext = $container->get(EnrichViewContextMiddleware::class);
$validateResources = $container->get(ValidateResourceAttributesMiddleware::class);
$permissions = $container->get(PermissionMiddleware::class);
$communityContext = $container->get(ResolveCommunityMiddleware::class);
$boardContext = $container->get(ResolveBoardMiddleware::class);
$threadContext = $container->get(ResolveThreadMiddleware::class);
$postContext = $container->get(ResolvePostMiddleware::class);

$router->addGlobalMiddleware($injectCurrentUser);
$router->addGlobalMiddleware($enrichViewContext);

$router->get('/', [$communityController, 'index']);
$router->post('/communities', [$communityController, 'store']);
$router->get('/login', [$authController, 'showLoginForm']);
$router->post('/login', [$authController, 'login']);
$router->get('/register', [$authController, 'showRegisterForm']);
$router->post('/register', [$authController, 'register']);
$router->post('/logout', [$authController, 'logout']);

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
    $searchController,
    $reactionController,
    $mentionController,
    $boardContext,
    $threadContext,
    $postContext,
    $permissions
) {
    $router->get('/', [$communityController, 'show']);
    $router->get('/about', [$communityController, 'about']);
    $router->get('/u/{username}', [$profileController, 'show']);

    $router->group('/settings', function (Router $router) use ($profileController) {
        $router->get('/profile', [$profileController, 'editProfile']);
        $router->post('/profile', [$profileController, 'updateProfile']);
        $router->get('/signature', [$profileController, 'editSignature']);
        $router->post('/signature', [$profileController, 'updateSignature']);
        $router->get('/avatar', [$profileController, 'editAvatar']);
        $router->post('/avatar', [$profileController, 'updateAvatar']);
    }, [$authRequired]);

    $router->get('/b/{board}', [$boardController, 'show'], [$boardContext]);
    $router->group('/b/{board}', function (Router $router) use ($threadController) {
        $router->get('/thread/new', [$threadController, 'create']);
        $router->post('/thread', [$threadController, 'store']);
    }, [$authRequired, $boardContext]);

    $router->get('/t/{thread}', [$threadController, 'show'], [$threadContext]);
    $router->post('/t/{thread}/reply', [$postController, 'store'], [$authRequired, $threadContext, $permissions->check('canReply')]);
    $router->post('/t/{thread}/lock', [$moderationController, 'lockThread'], [$authRequired, $threadContext, $permissions->check('canLockThread')]);
    $router->post('/t/{thread}/unlock', [$moderationController, 'unlockThread'], [$authRequired, $threadContext, $permissions->check('canLockThread')]);
    $router->post('/t/{thread}/sticky', [$moderationController, 'stickyThread'], [$authRequired, $threadContext, $permissions->check('canStickyThread')]);
    $router->post('/t/{thread}/unsticky', [$moderationController, 'unstickyThread'], [$authRequired, $threadContext, $permissions->check('canStickyThread')]);
    $router->post('/t/{thread}/announce', [$moderationController, 'announceThread'], [$authRequired, $threadContext, $permissions->check('canStickyThread')]); // Assuming announce uses sticky permission or similar?
    $router->post('/t/{thread}/unannounce', [$moderationController, 'unannounceThread'], [$authRequired, $threadContext, $permissions->check('canStickyThread')]);
    $router->post('/t/{thread}/move', [$moderationController, 'moveThread'], [$authRequired, $threadContext, $permissions->check('canMoveThread')]);
    $router->get('/p/{post}/edit', [$moderationController, 'editPost'], [$authRequired, $postContext, $permissions->check('canEditAnyPost')]);
    $router->post('/p/{post}/delete', [$moderationController, 'deletePost'], [$authRequired, $postContext, $permissions->check('canDeleteAnyPost')]);
    $router->post('/p/{post}/edit', [$moderationController, 'editPost'], [$authRequired, $postContext, $permissions->check('canEditAnyPost')]);
    $router->post('/p/{post}/report', [$moderationController, 'reportPost'], [$authRequired, $postContext]);
    $router->post('/p/{post}/react', [$reactionController, 'add'], [$authRequired, $postContext]);
    $router->get('/mentions', [$mentionController, 'inbox'], [$authRequired]);
    $router->post('/mentions/read', [$mentionController, 'markRead'], [$authRequired]);
    $router->post('/mentions/{mention}/read', [$mentionController, 'markOneRead'], [$authRequired]);
    $router->get('/mentions/suggest', [$mentionController, 'suggest'], [$authRequired]);

    $router->get('/admin/bans', [$moderationController, 'listBans'], [$authRequired, $permissions->check('canBan')]);
    $router->post('/admin/bans', [$moderationController, 'createBan'], [$authRequired, $permissions->check('canBan')]);
    $router->post('/admin/bans/{ban}/delete', [$moderationController, 'deleteBan'], [$authRequired, $permissions->check('canBan')]);
    $router->get('/search', [$searchController, 'search']);

    $router->group('/admin', function (Router $router) use ($adminController) {
        $router->get('/structure', [$adminController, 'structure']);
        $router->post('/custom-css', [$adminController, 'updateCommunityCss']);
        $router->post('/categories', [$adminController, 'createCategory']);
        $router->post('/categories/reorder', [$adminController, 'reorderCategories']);
        $router->post('/categories/{category}', [$adminController, 'updateCategory']);
        $router->post('/categories/{category}/delete', [$adminController, 'deleteCategory']);
        $router->post('/boards', [$adminController, 'createBoard']);
        $router->post('/boards/reorder', [$adminController, 'reorderBoards']);
        $router->post('/boards/{board}', [$adminController, 'updateBoard']);
        $router->post('/boards/{board}/delete', [$adminController, 'deleteBoard']);
        $router->post('/moderators', [$adminController, 'addModerator']);
        $router->post('/moderators/{user}/delete', [$adminController, 'removeModerator']);
        $router->get('/reports', [$adminController, 'reports']);
        $router->post('/reports/{report}/resolve', [$adminController, 'resolveReport']);
        $router->get('/settings', [$adminController, 'settings']);
        $router->post('/settings', [$adminController, 'updateSettings']);
        $router->get('/users', [$adminController, 'users']);
    }, [$authRequired, $permissions->check('canModerate')]);
}, [$communityContext, $validateResources]);

$router->setNotFoundHandler(function (Request $request) use ($container) {
    $view = $container->get(ViewRenderer::class);
    $auth = $container->get(AuthService::class);
    $config = $container->get(AppConfig::class);

    $body = $view->render('errors/404.php', [
        'pageTitle' => 'Page not found',
        'path' => $request->path,
    ]);

    return new Response(
        status: 404,
        headers: ['Content-Type' => 'text/html; charset=utf-8'],
        body: $body,
    );
});

$request = Request::fromGlobals();

$csrf = $container->get(CsrfGuard::class);
if ($request->method === 'POST' && !$csrf->isValid($request)) {
    try {
        $view = $container->get(ViewRenderer::class);
        $config = $container->get(AppConfig::class);
        $auth = $container->get(AuthService::class);

        $body = $view->render('errors/419.php', [
            'pageTitle' => 'CSRF token mismatch',
        ]);
    } catch (\Throwable) {
        $body = '<h1>CSRF token mismatch</h1>';
    }

    (new Response(
        status: 419,
        headers: ['Content-Type' => 'text/html; charset=utf-8'],
        body: $body,
    ))->send();
    exit;
}

$navResponse = $navigationTracker->track($request, $_SESSION);
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
