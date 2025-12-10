<?php

declare(strict_types=1);

use Fred\Application\Auth\AuthService;
use Fred\Http\Controller\AdminController;
use Fred\Http\Controller\AuthController;
use Fred\Http\Controller\BoardController;
use Fred\Http\Controller\CommunityController;
use Fred\Http\Controller\MentionController;
use Fred\Http\Controller\ModerationController;
use Fred\Http\Controller\PostController;
use Fred\Http\Controller\ProfileController;
use Fred\Http\Controller\ReactionController;
use Fred\Http\Controller\SearchController;
use Fred\Http\Controller\ThreadController;
use Fred\Http\Controller\UploadController;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Http\Routing\Router;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewRenderer;
use League\Container\Container;

return static function (Router $router, Container $container): void {
    $router->addGlobalMiddleware('currentUser');
    $router->addGlobalMiddleware('view.enrich');

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
        $moderationController,
        $searchController,
        $reactionController,
        $mentionController,
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
        }, ['auth']);

        $router->group('/b/{board}', function (Router $router) use (
            $boardController,
            $threadController,
        ) {
            $router->get('/', [$boardController, 'show'], ['ctx.board']);

            $router->group('', function (Router $router) use ($threadController) {
                $router->get('/thread/new', [$threadController, 'create']);
                $router->post('/thread', [$threadController, 'store']);
            }, ['auth', 'ctx.board']);
        });

        $router->group('/t/{thread}', function (Router $router) use (
            $threadController,
            $postController,
            $moderationController,
        ) {
            $router->get('/', [$threadController, 'show'], ['ctx.thread']);
            $router->get('/previews', [$threadController, 'previews'], ['ctx.thread']);

            $router->post('/reply', [$postController, 'store'], ['auth', 'ctx.thread', 'perm.canReply']);
            $router->post('/lock', [$moderationController, 'lockThread'], ['auth', 'ctx.thread', 'perm.canLockThread']);
            $router->post('/unlock', [$moderationController, 'unlockThread'], ['auth', 'ctx.thread', 'perm.canLockThread']);
            $router->post('/sticky', [$moderationController, 'stickyThread'], ['auth', 'ctx.thread', 'perm.canStickyThread']);
            $router->post('/unsticky', [$moderationController, 'unstickyThread'], ['auth', 'ctx.thread', 'perm.canStickyThread']);
            $router->post('/announce', [$moderationController, 'announceThread'], ['auth', 'ctx.thread', 'perm.canStickyThread']);
            $router->post('/unannounce', [$moderationController, 'unannounceThread'], ['auth', 'ctx.thread', 'perm.canStickyThread']);
            $router->post('/move', [$moderationController, 'moveThread'], ['auth', 'ctx.thread', 'perm.canMoveThread']);
        });

        $router->group('/p/{post}', function (Router $router) use (
            $moderationController,
            $reactionController,
        ) {
            $router->get('/edit', [$moderationController, 'editPost'], ['auth', 'ctx.post', 'perm.canEditAnyPost']);
            $router->post('/delete', [$moderationController, 'deletePost'], ['auth', 'ctx.post', 'perm.canDeleteAnyPost']);
            $router->post('/edit', [$moderationController, 'editPost'], ['auth', 'ctx.post', 'perm.canEditAnyPost']);
            $router->post('/report', [$moderationController, 'reportPost'], ['auth', 'ctx.post']);
            $router->post('/react', [$reactionController, 'add'], ['auth', 'ctx.post']);
        });

        $router->group('/mentions', function (Router $router) use ($mentionController) {
            $router->get('/', [$mentionController, 'inbox']);
            $router->post('/read', [$mentionController, 'markRead']);
            $router->post('/{mention}/read', [$mentionController, 'markOneRead']);
            $router->get('/suggest', [$mentionController, 'suggest']);
        }, ['auth']);

        $router->get('/search', [$searchController, 'search']);

        $router->group('/admin/bans', function (Router $router) use ($moderationController) {
            $router->get('/', [$moderationController, 'listBans']);
            $router->post('/', [$moderationController, 'createBan']);
            $router->post('/{ban}/delete', [$moderationController, 'deleteBan']);
        }, ['auth', 'perm.canBan']);

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
        }, ['auth', 'perm.canModerate']);
    }, ['ctx.community']);

    $router->setNotFoundHandler(function (Request $request) use ($container) {
        $view = $container->get(ViewRenderer::class);
        $config = $container->get(AppConfig::class);
        $auth = $container->get(AuthService::class);

        return Response::notFound(
            view: $view,
            config: $config,
            auth: $auth,
            request: $request,
            context: "Route not found for path: {$request->path}"
        );
    });
};
