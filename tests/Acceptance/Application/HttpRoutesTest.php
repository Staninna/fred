<?php

declare(strict_types=1);

namespace Tests\Acceptance\Application;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Application\Content\BbcodeParser;
use Fred\Application\Content\CreateReplyService;
use Fred\Application\Content\CreateThreadService;
use Fred\Application\Content\EditPostService;
use Fred\Application\Content\EmoticonSet;
use Fred\Application\Content\LinkPreviewer;
use Fred\Application\Content\MentionService;
use Fred\Application\Content\ThreadStateService;
use Fred\Application\Content\UploadService;
use Fred\Http\Controller\AdminController;
use Fred\Http\Controller\AuthController;
use Fred\Http\Controller\BoardController;
use Fred\Http\Controller\CommunityController;
use Fred\Http\Controller\ModerationController;
use Fred\Http\Controller\PostController;
use Fred\Http\Controller\ThreadController;
use Fred\Http\Middleware\ResolveBoardMiddleware;
use Fred\Http\Middleware\ResolveCommunityMiddleware;
use Fred\Http\Middleware\ResolvePostMiddleware;
use Fred\Http\Middleware\ResolveThreadMiddleware;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Http\Routing\Router;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\AttachmentRepository;
use Fred\Infrastructure\Database\BanRepository;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityModeratorRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\MentionNotificationRepository;
use Fred\Infrastructure\Database\PermissionRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\ReactionRepository;
use Fred\Infrastructure\Database\ReportRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\View\ViewRenderer;
use Tests\TestCase;

final class HttpRoutesTest extends TestCase
{
    public function testCommunityAndBoardPagesRenderData(): void
    {
        [$router, $context] = $this->buildApp();

        $communitySlug = $context['community_slug'];
        $boardSlug = $context['board_slug'];
        $threadId = $context['thread_id'];
        $threadTitle = $context['thread_title'];
        $postBody = $context['post_body'];

        $communityResponse = $this->dispatch($context, new Request(
            method: 'GET',
            path: '/c/' . $communitySlug,
            query: [],
            body: [],
        ), $router);
        $this->assertSame(200, $communityResponse->status);
        $this->assertStringContainsString('Main Plaza', $communityResponse->body);

        $aboutResponse = $this->dispatch($context, new Request(
            method: 'GET',
            path: '/c/' . $communitySlug . '/about',
            query: [],
            body: [],
        ), $router);
        $this->assertSame(200, $aboutResponse->status);
        $this->assertStringContainsString('About', $aboutResponse->body);

        $boardResponse = $this->dispatch($context, new Request(
            method: 'GET',
            path: '/c/' . $communitySlug . '/b/' . $boardSlug,
            query: [],
            body: [],
        ), $router);
        $this->assertSame(200, $boardResponse->status);
        $this->assertStringContainsString($threadTitle, $boardResponse->body);

        $threadResponse = $this->dispatch($context, new Request(
            method: 'GET',
            path: '/c/' . $communitySlug . '/t/' . $threadId,
            query: [],
            body: [],
        ), $router);
        $this->assertSame(200, $threadResponse->status);
        $this->assertStringContainsString($postBody, $threadResponse->body);
    }

    public function testCommunityRouteDoesNot404WithOrWithoutTrailingSlash(): void
    {
        [$router, $context] = $this->buildApp();

        $communitySlug = $context['community_slug'];

        $withoutSlash = $this->dispatch($context, new Request(
            method: 'GET',
            path: '/c/' . $communitySlug,
            query: [],
            body: [],
        ), $router);
        $this->assertSame(200, $withoutSlash->status);

        $withSlash = $this->dispatch($context, new Request(
            method: 'GET',
            path: '/c/' . $communitySlug . '/',
            query: [],
            body: [],
        ), $router);
        $this->assertSame(200, $withSlash->status);
    }

    /**
     * @return array{0: Router, 1: array<string, mixed>}
     */
    private function buildApp(): array
    {
        $pdo = $this->makeMigratedPdo();

        $view = new ViewRenderer($this->basePath('resources/views'));
        $config = new AppConfig(
            environment: 'testing',
            baseUrl: 'http://localhost',
            databasePath: ':memory:',
            uploadsPath: $this->createTempDir('fred-uploads-'),
            logsPath: $this->createTempDir('fred-logs-'),
            basePath: $this->basePath(),
        );

        $userRepository = new UserRepository($pdo);
        $roleRepository = new RoleRepository($pdo);
        $communityRepository = new CommunityRepository($pdo);
        $categoryRepository = new CategoryRepository($pdo);
        $boardRepository = new BoardRepository($pdo);
        $threadRepository = new ThreadRepository($pdo);
        $postRepository = new PostRepository($pdo);
        $profileRepository = new ProfileRepository($pdo);
        $attachmentRepository = new AttachmentRepository($pdo);
        $reportRepository = new ReportRepository($pdo);
        $uploadService = new UploadService($config);
        $authService = new AuthService(
            users: $userRepository,
            roles: $roleRepository,
            bans: new BanRepository($pdo),
        );
        $permissionService = new PermissionService(new PermissionRepository($pdo), new CommunityModeratorRepository($pdo));
        $communityContext = new CommunityContext($communityRepository, $categoryRepository, $boardRepository);

        $router = new Router($this->basePath('public'));
        $communityContextMiddleware = new ResolveCommunityMiddleware($communityContext, $view, $config);
        $boardContext = new ResolveBoardMiddleware($communityContext, $categoryRepository, $view, $config);
        $threadContext = new ResolveThreadMiddleware($boardRepository, $threadRepository, $categoryRepository, $view, $config);
        $postContext = new ResolvePostMiddleware($postRepository, $threadRepository, $boardRepository, $categoryRepository, $view, $config);
        $authController = new AuthController($view, $config, $authService);
        $communityController = new CommunityController(
            $view,
            $config,
            $authService,
            $communityContext,
            $permissionService,
            $communityRepository,
            $categoryRepository,
            $boardRepository,
        );
        $adminController = new AdminController(
            $view,
            $config,
            $authService,
            $permissionService, // Corrected: should be PermissionService
            $communityContext,
            $categoryRepository,
            $boardRepository,
            $communityRepository,
            new CommunityModeratorRepository($pdo), // Missing
            $userRepository, // Missing
            $roleRepository, // Missing
            $reportRepository,
        );
        $boardController = new BoardController(
            $view,
            $config,
            $authService,
            $communityContext,
            $permissionService,
            $boardRepository,
            $categoryRepository,
            $threadRepository,
        );
        $threadController = new ThreadController(
            $view,
            $config,
            $authService,
            $communityContext,
            $permissionService,
            $categoryRepository,
            $boardRepository,
            $threadRepository,
            $postRepository,
            new BbcodeParser(),
            new LinkPreviewer($config),
            $userRepository,
            $profileRepository,
            $attachmentRepository,
            new ReactionRepository($pdo),
            new MentionNotificationRepository($pdo),
            new EmoticonSet($config),
            new CreateThreadService($permissionService, $threadRepository, $postRepository, new BbcodeParser(), $profileRepository, $uploadService, $attachmentRepository, new MentionService($userRepository, new MentionNotificationRepository($pdo)), $pdo),
        );
        $postController = new PostController(
            $view,
            $config,
            $authService,
            $communityContext,
            new CreateReplyService($permissionService, $threadRepository, $postRepository, new BbcodeParser(), $profileRepository, $uploadService, $attachmentRepository, new MentionService($userRepository, new MentionNotificationRepository($pdo))),
        );
        $moderationController = new ModerationController(
            $view,
            $config,
            $authService,
            $communityContext,
            $permissionService,
            $threadRepository,
            $postRepository,
            new BbcodeParser(),
            $userRepository,
            new BanRepository($pdo),
            $boardRepository,
            $categoryRepository,
            $reportRepository,
            $attachmentRepository,
            $uploadService,
            new MentionService($userRepository, new MentionNotificationRepository($pdo)),
            new ThreadStateService($permissionService, $threadRepository),
            new EditPostService($permissionService, $postRepository, new BbcodeParser(), new MentionService($userRepository, new MentionNotificationRepository($pdo))),
            new \Fred\Application\Content\DeletePostService($permissionService, $postRepository, new \Fred\Application\Content\AttachmentCleanupHelper($attachmentRepository, $uploadService, $postRepository)),
            new \Fred\Application\Content\MoveThreadService($permissionService, $threadRepository, $boardRepository),
            new \Fred\Application\Content\ReportPostService($reportRepository),
            new \Fred\Application\Moderation\CreateBanService($userRepository, new BanRepository($pdo)),
            new \Fred\Application\Moderation\DeleteBanService(new BanRepository($pdo)),
        );

        $router->get('/', [$communityController, 'index']);
        $router->post('/communities', [$communityController, 'store']);
        $router->get('/c/{community}', [$communityController, 'show'], [$communityContextMiddleware]);
        $router->get('/c/{community}/about', [$communityController, 'about'], [$communityContextMiddleware]);
        $router->get('/c/{community}/b/{board}', [$boardController, 'show'], [$communityContextMiddleware, $boardContext]);
        $router->get('/c/{community}/b/{board}/thread/new', [$threadController, 'create'], [$communityContextMiddleware, $boardContext]);
        $router->post('/c/{community}/b/{board}/thread', [$threadController, 'store'], [$communityContextMiddleware, $boardContext]);
        $router->get('/c/{community}/t/{thread}', [$threadController, 'show'], [$communityContextMiddleware, $threadContext]);
        $router->post('/c/{community}/t/{thread}/reply', [$postController, 'store'], [$communityContextMiddleware, $threadContext]);
        $router->post('/c/{community}/t/{thread}/lock', [$moderationController, 'lockThread'], [$communityContextMiddleware, $threadContext]);
        $router->post('/c/{community}/t/{thread}/unlock', [$moderationController, 'unlockThread'], [$communityContextMiddleware, $threadContext]);
        $router->post('/c/{community}/t/{thread}/sticky', [$moderationController, 'stickyThread'], [$communityContextMiddleware, $threadContext]);
        $router->post('/c/{community}/t/{thread}/unsticky', [$moderationController, 'unstickyThread'], [$communityContextMiddleware, $threadContext]);
        $router->post('/c/{community}/t/{thread}/move', [$moderationController, 'moveThread'], [$communityContextMiddleware, $threadContext]);
        $router->get('/c/{community}/p/{post}/edit', [$moderationController, 'editPost'], [$communityContextMiddleware, $postContext]);
        $router->post('/c/{community}/p/{post}/delete', [$moderationController, 'deletePost'], [$communityContextMiddleware, $postContext]);
        $router->post('/c/{community}/p/{post}/edit', [$moderationController, 'editPost'], [$communityContextMiddleware, $postContext]);
        $router->get('/c/{community}/admin/bans', [$moderationController, 'listBans'], [$communityContextMiddleware]);
        $router->post('/c/{community}/admin/bans', [$moderationController, 'createBan'], [$communityContextMiddleware]);
        $router->post('/c/{community}/admin/bans/{ban}/delete', [$moderationController, 'deleteBan'], [$communityContextMiddleware]);
        $router->get('/c/{community}/admin/structure', [$adminController, 'structure'], [$communityContextMiddleware]);
        $router->post('/c/{community}/admin/categories', [$adminController, 'createCategory'], [$communityContextMiddleware]);
        $router->post('/c/{community}/admin/categories/{category}', [$adminController, 'updateCategory'], [$communityContextMiddleware]);
        $router->post('/c/{community}/admin/categories/{category}/delete', [$adminController, 'deleteCategory'], [$communityContextMiddleware]);
        $router->post('/c/{community}/admin/boards', [$adminController, 'createBoard'], [$communityContextMiddleware]);
        $router->post('/c/{community}/admin/boards/{board}', [$adminController, 'updateBoard'], [$communityContextMiddleware, $boardContext]);
        $router->post('/c/{community}/admin/boards/{board}/delete', [$adminController, 'deleteBoard'], [$communityContextMiddleware, $boardContext]);
        $router->get('/login', [$authController, 'showLoginForm']);
        $router->post('/login', [$authController, 'login']);
        $router->get('/register', [$authController, 'showRegisterForm']);
        $router->post('/register', [$authController, 'register']);
        $router->post('/logout', [$authController, 'logout']);

        $seed = $this->seedForumData(
            $communityRepository,
            $categoryRepository,
            $boardRepository,
            $userRepository,
            $roleRepository,
            $threadRepository,
            $postRepository,
        );

        return [$router, $seed + [
            'view' => $view,
            'auth' => $authService,
            'config' => $config,
            'communityContext' => $communityContext,
        ]];
    }

    private function seedForumData(
        CommunityRepository $communities,
        CategoryRepository $categories,
        BoardRepository $boards,
        UserRepository $users,
        RoleRepository $roles,
        ThreadRepository $threads,
        PostRepository $posts,
    ): array {
        $roles->ensureDefaultRoles();
        $member = $roles->findBySlug('member');
        assert($member !== null);

        $user = $users->create('tester', 'Test User', password_hash('secret', PASSWORD_BCRYPT), $member->id, time());

        $community = $communities->create('main', 'Main Plaza', 'A cozy square', null, time());
        $category = $categories->create($community->id, 'Lobby', 1, time());
        $board = $boards->create(
            communityId: $community->id,
            categoryId: $category->id,
            slug: 'general',
            name: 'General Lounge',
            description: 'Chat freely',
            position: 1,
            isLocked: false,
            customCss: null,
            timestamp: time(),
        );

        $thread = $threads->create(
            communityId: $community->id,
            boardId: $board->id,
            title: 'Welcome aboard',
            authorId: $user->id,
            isSticky: false,
            isLocked: false,
            isAnnouncement: false,
            timestamp: time(),
        );

        $posts->create(
            communityId: $community->id,
            threadId: $thread->id,
            authorId: $user->id,
            bodyRaw: 'First message',
            bodyParsed: null,
            signatureSnapshot: null,
            timestamp: time(),
        );

        return [
            'community_slug' => $community->slug,
            'board_slug' => $board->slug,
            'thread_id' => $thread->id,
            'thread_title' => $thread->title,
            'post_body' => 'First message',
        ];
    }

    private function dispatch(array $context, Request $request, Router $router): Response
    {
        $view = $context['view'];
        $auth = $context['auth'];
        $config = $context['config'];
        $communityContext = $context['communityContext'];

        $view->share('currentUser', $auth->currentUser());
        $view->share('environment', $config->environment);
        $view->share('baseUrl', $config->baseUrl);
        $view->share('activePath', $request->path);
        $view->share('navSections', $communityContext->navSections(null, [], []));
        $view->share('currentCommunity', null);
        $view->share('customCss', '');

        return $router->dispatch($request);
    }
}
