<?php

declare(strict_types=1);

use Fred\Http\Controller\HealthController;
use Fred\Http\Controller\HomeController;
use Fred\Http\Controller\AuthController;
use Fred\Http\Controller\AdminController;
use Fred\Http\Controller\CommunityController;
use Fred\Http\Controller\BoardController;
use Fred\Http\Controller\ThreadController;
use Fred\Http\Controller\PostController;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Http\Routing\Router;
use Fred\Application\Auth\AuthService;
use Fred\Infrastructure\Config\ConfigLoader;
use Fred\Infrastructure\Database\ConnectionFactory;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Env\DotenvLoader;
use Fred\Infrastructure\Session\SqliteSessionHandler;
use Fred\Infrastructure\View\ViewRenderer;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
$env = DotenvLoader::load($basePath . '/.env');
$config = ConfigLoader::fromArray($env, $basePath);
$pdo = ConnectionFactory::make($config);
$userRepository = new UserRepository($pdo);
$roleRepository = new RoleRepository($pdo);
$communityRepository = new CommunityRepository($pdo);
$categoryRepository = new CategoryRepository($pdo);
$boardRepository = new BoardRepository($pdo);
$threadRepository = new ThreadRepository($pdo);
$postRepository = new PostRepository($pdo);
$authService = new AuthService($userRepository, $roleRepository);

$sessionHandler = new SqliteSessionHandler($pdo);
session_set_save_handler($sessionHandler, true);
session_start();

$view = new ViewRenderer($basePath . '/resources/views');
$router = new Router($basePath . '/public');
$homeController = new HomeController($view, $config, $authService);
$healthController = new HealthController($view, $config, $authService);
$authController = new AuthController($view, $config, $authService);
$communityController = new CommunityController(
    $view,
    $config,
    $authService,
    $communityRepository,
    $categoryRepository,
    $boardRepository,
);
$adminController = new AdminController(
    $view,
    $config,
    $authService,
    $communityRepository,
    $categoryRepository,
    $boardRepository,
);
$boardController = new BoardController(
    $view,
    $config,
    $authService,
    $communityRepository,
    $categoryRepository,
    $boardRepository,
    $threadRepository,
);
$threadController = new ThreadController(
    $view,
    $config,
    $authService,
    $communityRepository,
    $categoryRepository,
    $boardRepository,
    $threadRepository,
    $postRepository,
);
$postController = new PostController(
    $authService,
    $communityRepository,
    $boardRepository,
    $threadRepository,
    $postRepository,
);

$router->get('/', [$communityController, 'index']);
$router->post('/communities', [$communityController, 'store']);
$router->get('/c/{community}', [$communityController, 'show']);
$router->get('/c/{community}/b/{board}', [$boardController, 'show']);
$router->get('/c/{community}/b/{board}/thread/new', [$threadController, 'create']);
$router->post('/c/{community}/b/{board}/thread', [$threadController, 'store']);
$router->get('/c/{community}/t/{thread}', [$threadController, 'show']);
$router->post('/c/{community}/t/{thread}/reply', [$postController, 'store']);
$router->get('/c/{community}/admin/structure', [$adminController, 'structure']);
$router->post('/c/{community}/admin/categories', [$adminController, 'createCategory']);
$router->post('/c/{community}/admin/categories/{category}', [$adminController, 'updateCategory']);
$router->post('/c/{community}/admin/categories/{category}/delete', [$adminController, 'deleteCategory']);
$router->post('/c/{community}/admin/boards', [$adminController, 'createBoard']);
$router->post('/c/{community}/admin/boards/{board}', [$adminController, 'updateBoard']);
$router->post('/c/{community}/admin/boards/{board}/delete', [$adminController, 'deleteBoard']);
$router->get('/health', [$healthController, 'show']);
$router->get('/login', [$authController, 'showLoginForm']);
$router->post('/login', [$authController, 'login']);
$router->get('/register', [$authController, 'showRegisterForm']);
$router->post('/register', [$authController, 'register']);
$router->post('/logout', [$authController, 'logout']);

$router->setNotFoundHandler(function (Request $request) use ($view, $authService, $config) {
    $body = $view->render('errors/404.php', [
        'pageTitle' => 'Page not found',
        'path' => $request->path,
        'activePath' => $request->path,
        'environment' => $config->environment,
        'currentUser' => $authService->currentUser(),
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
            'environment' => $config->environment,
            'currentUser' => $authService->currentUser(),
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
