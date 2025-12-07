<?php

declare(strict_types=1);

namespace Tests\Acceptance\Application;

use Fred\Application\Auth\AuthService;
use Fred\Http\Controller\AdminController;
use Fred\Http\Controller\AuthController;
use Fred\Http\Controller\BoardController;
use Fred\Http\Controller\CommunityHelper;
use Fred\Http\Controller\CommunityController;
use Fred\Http\Controller\ModerationController;
use Fred\Http\Controller\PostController;
use Fred\Http\Controller\ThreadController;
use Fred\Http\Request;
use Fred\Http\Routing\Router;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\Database\PermissionRepository;
use Fred\Infrastructure\Database\CommunityModeratorRepository;
use Fred\Infrastructure\View\ViewRenderer;
use Fred\Application\Content\BbcodeParser;
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

        $communityResponse = $router->dispatch(new Request(
            method: 'GET',
            path: '/c/' . $communitySlug,
            query: [],
            body: [],
        ));
        $this->assertSame(200, $communityResponse->status);
        $this->assertStringContainsString('Main Plaza', $communityResponse->body);

        $aboutResponse = $router->dispatch(new Request(
            method: 'GET',
            path: '/c/' . $communitySlug . '/about',
            query: [],
            body: [],
        ));
        $this->assertSame(200, $aboutResponse->status);
        $this->assertStringContainsString('About', $aboutResponse->body);

        $boardResponse = $router->dispatch(new Request(
            method: 'GET',
            path: '/c/' . $communitySlug . '/b/' . $boardSlug,
            query: [],
            body: [],
        ));
        $this->assertSame(200, $boardResponse->status);
        $this->assertStringContainsString($threadTitle, $boardResponse->body);

        $threadResponse = $router->dispatch(new Request(
            method: 'GET',
            path: '/c/' . $communitySlug . '/t/' . $threadId,
            query: [],
            body: [],
        ));
        $this->assertSame(200, $threadResponse->status);
        $this->assertStringContainsString($postBody, $threadResponse->body);
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
        $attachmentRepository = new \Fred\Infrastructure\Database\AttachmentRepository($pdo);
        $reportRepository = new \Fred\Infrastructure\Database\ReportRepository($pdo);
        $uploadService = new \Fred\Application\Content\UploadService($config);
        $authService = new AuthService(
            users: $userRepository,
            roles: $roleRepository,
            bans: new \Fred\Infrastructure\Database\BanRepository($pdo),
        );
        $permissionService = new \Fred\Application\Auth\PermissionService(new PermissionRepository($pdo), new CommunityModeratorRepository($pdo));
        $communityHelper = new CommunityHelper($communityRepository, $categoryRepository, $boardRepository);

        $router = new Router($this->basePath('public'));
        $authController = new AuthController($view, $config, $authService, $communityHelper);
        $communityController = new CommunityController(
            $view,
            $config,
            $authService,
            $permissionService, // Corrected: should be PermissionService
            $communityHelper,
            $communityRepository, // Added: missing CommunityRepository
        );
        $adminController = new AdminController(
            $view,
            $config,
            $authService,
            $permissionService, // Corrected: should be PermissionService
            $communityHelper,
            $categoryRepository,
            $boardRepository,
            $communityRepository,
            new \Fred\Infrastructure\Database\CommunityModeratorRepository($pdo), // Missing
            $userRepository, // Missing
            $roleRepository, // Missing
            $reportRepository,
        );
        $boardController = new BoardController(
            $view,
            $config,
            $authService,
            $permissionService, // Corrected: should be PermissionService
            $communityHelper,
            $categoryRepository,
            $threadRepository,
        );
        $threadController = new ThreadController(
            $view,
            $config,
            $authService,
            $permissionService,
            $communityHelper,
            $categoryRepository,
            $threadRepository,
            $postRepository,
            new BbcodeParser(),
            $profileRepository,
            $uploadService,
            $attachmentRepository,
        );
        $postController = new PostController(
            $authService,
            $view,
            $config,
            $communityHelper,
            $threadRepository,
            $postRepository,
            new BbcodeParser(),
            $profileRepository,
            $permissionService,
            $uploadService,
            $attachmentRepository,
        );
        $moderationController = new ModerationController(
            $view,
            $config,
            $authService,
            $permissionService,
            $communityHelper,
            $threadRepository,
            $postRepository,
            new \Fred\Application\Content\BbcodeParser(), // Missing
            $userRepository, // Missing
            new \Fred\Infrastructure\Database\BanRepository($pdo), // Missing
            $boardRepository, // Missing
            $categoryRepository, // Missing
            $reportRepository,
        );

        $router->get('/', [$communityController, 'index']);
        $router->post('/communities', [$communityController, 'store']);
        $router->get('/c/{community}', [$communityController, 'show']);
        $router->get('/c/{community}/about', [$communityController, 'about']);
        $router->get('/c/{community}/b/{board}', [$boardController, 'show']);
        $router->get('/c/{community}/b/{board}/thread/new', [$threadController, 'create']);
        $router->post('/c/{community}/b/{board}/thread', [$threadController, 'store']);
        $router->get('/c/{community}/t/{thread}', [$threadController, 'show']);
        $router->post('/c/{community}/t/{thread}/reply', [$postController, 'store']);
        $router->post('/c/{community}/t/{thread}/lock', [$moderationController, 'lockThread']);
        $router->post('/c/{community}/t/{thread}/unlock', [$moderationController, 'unlockThread']);
        $router->post('/c/{community}/t/{thread}/sticky', [$moderationController, 'stickyThread']);
        $router->post('/c/{community}/t/{thread}/unsticky', [$moderationController, 'unstickyThread']);
        $router->post('/c/{community}/t/{thread}/move', [$moderationController, 'moveThread']);
        $router->get('/c/{community}/p/{post}/edit', [$moderationController, 'editPost']);
        $router->post('/c/{community}/p/{post}/delete', [$moderationController, 'deletePost']);
        $router->post('/c/{community}/p/{post}/edit', [$moderationController, 'editPost']);
        $router->get('/c/{community}/admin/bans', [$moderationController, 'listBans']);
        $router->post('/c/{community}/admin/bans', [$moderationController, 'createBan']);
        $router->post('/c/{community}/admin/bans/{ban}/delete', [$moderationController, 'deleteBan']);
        $router->get('/c/{community}/admin/structure', [$adminController, 'structure']);
        $router->post('/c/{community}/admin/categories', [$adminController, 'createCategory']);
        $router->post('/c/{community}/admin/categories/{category}', [$adminController, 'updateCategory']);
        $router->post('/c/{community}/admin/categories/{category}/delete', [$adminController, 'deleteCategory']);
        $router->post('/c/{community}/admin/boards', [$adminController, 'createBoard']);
        $router->post('/c/{community}/admin/boards/{board}', [$adminController, 'updateBoard']);
        $router->post('/c/{community}/admin/boards/{board}/delete', [$adminController, 'deleteBoard']);
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

        return [$router, $seed];
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
}
