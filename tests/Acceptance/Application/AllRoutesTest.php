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
use Fred\Http\Controller\ProfileController;
use Fred\Http\Controller\SearchController;
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
use Fred\Application\Search\SearchService;
use Tests\TestCase;

final class AllRoutesTest extends TestCase
{
    /**
     * @dataProvider roleProvider
     */
    public function testAllGetRoutesDoNotProduceServerErrors(string $role): void
    {
        session_start();
        [$router, $context] = $this->buildApp();

        $userId = $context[$role . '_id'] ?? null;
        $_SESSION['user_id'] = $userId;

        $routes = $router->getRoutes();

        $getRoutes = array_merge(
            array_keys($routes['static']['GET'] ?? []),
            array_column($routes['dynamic']['GET'] ?? [], 'path')
        );

        $replacements = [
            '{community}' => $context['community_slug'],
            '{board}' => $context['board_slug'],
            '{thread}' => (string) $context['thread_id'],
            '{post}' => (string) $context['post_id'],
            '{username}' => $context['member_username'],
            '{ban}' => (string) $context['ban_id'],
            '{category}' => (string) $context['category_id'],
        ];

        $errors = [];

        foreach ($getRoutes as $route) {
            $path = str_replace(array_keys($replacements), array_values($replacements), $route);

            // Skip routes with remaining placeholders that are not optional
            if (str_contains($path, '{')) {
                continue;
            }

            $response = $router->dispatch(new Request(
                method: 'GET',
                path: $path,
                query: [],
                body: [],
            ));

            if ($response->status >= 500) {
                $errors[] = "[$role] Route $path returned status " . $response->status;
            }

            if (str_contains($response->body, 'Server error') || str_contains($response->body, 'exception_message')) {
                $errors[] = "[$role] Route $path returned a server error message.";
            }
        }

        $this->assertEmpty($errors, "Server errors detected on the following routes:\n" . implode("\n", $errors));
    }

    public static function roleProvider(): array
    {
        return [
            'guest' => ['guest'],
            'member' => ['member'],
            'moderator' => ['moderator'],
            'admin' => ['admin'],
        ];
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
        $permissionRepository = new PermissionRepository($pdo);
        $communityModeratorRepository = new CommunityModeratorRepository($pdo);
        $banRepository = new \Fred\Infrastructure\Database\BanRepository($pdo);
        $attachmentRepository = new \Fred\Infrastructure\Database\AttachmentRepository($pdo);
        $uploadService = new \Fred\Application\Content\UploadService($config);
        $authService = new AuthService(
            users: $userRepository,
            roles: $roleRepository,
            bans: $banRepository,
        );
        $permissionService = new \Fred\Application\Auth\PermissionService($permissionRepository, $communityModeratorRepository);
        $communityHelper = new CommunityHelper($communityRepository, $categoryRepository, $boardRepository);
        $searchService = new SearchService($pdo);

        $router = new Router($this->basePath('public'));

        $authController = new AuthController($view, $config, $authService, $communityHelper);
        $communityController = new CommunityController($view, $config, $authService, $permissionService, $communityHelper, $communityRepository);
        $reportRepository = new \Fred\Infrastructure\Database\ReportRepository($pdo);
        $adminController = new AdminController($view, $config, $authService, $permissionService, $communityHelper, $categoryRepository, $boardRepository, $communityRepository, $communityModeratorRepository, $userRepository, $roleRepository, $reportRepository);
        $boardController = new BoardController($view, $config, $authService, $permissionService, $communityHelper, $categoryRepository, $threadRepository);
        $threadController = new ThreadController($view, $config, $authService, $permissionService, $communityHelper, $categoryRepository, $threadRepository, $postRepository, new BbcodeParser(), $profileRepository, $uploadService, $attachmentRepository);
        $postController = new PostController($authService, $view, $config, $communityHelper, $threadRepository, $postRepository, new BbcodeParser(), $profileRepository, $permissionService, $uploadService, $attachmentRepository);
        $moderationController = new ModerationController($view, $config, $authService, $permissionService, $communityHelper, $threadRepository, $postRepository, new BbcodeParser(), $userRepository, $banRepository, $boardRepository, $categoryRepository, $reportRepository);
        $profileController = new ProfileController($view, $config, $authService, $communityHelper, $userRepository, $profileRepository, new BbcodeParser(), $uploadService);
        $searchController = new SearchController($view, $config, $authService, $permissionService, $communityHelper, $searchService, $userRepository);

        // This is a partial copy from public/index.php. Ideally, this would be refactored.
        $router->get('/', [$communityController, 'index']);
        $router->post('/communities', [$communityController, 'store']);
        $router->get('/login', [$authController, 'showLoginForm']);
        $router->post('/login', [$authController, 'login']);
        $router->get('/register', [$authController, 'showRegisterForm']);
        $router->post('/register', [$authController, 'register']);
        $router->post('/logout', [$authController, 'logout']);

        $router->get('/c/{community}', [$communityController, 'show']);

        $router->group('/c/{community}', function (Router $router) use (
            $communityController,
            $boardController,
            $threadController,
            $postController,
            $adminController,
            $profileController,
            $moderationController,
            $searchController
        ) {
            $router->get('/', [$communityController, 'show']);
            $router->get('/u/{username}', [$profileController, 'show']);
            $router->get('/settings/profile', [$profileController, 'editProfile']);
            $router->post('/settings/profile', [$profileController, 'updateProfile']);
            $router->get('/settings/signature', [$profileController, 'editSignature']);
            $router->post('/settings/signature', [$profileController, 'updateSignature']);
            $router->get('/b/{board}', [$boardController, 'show']);
            $router->get('/b/{board}/thread/new', [$threadController, 'create']);
            $router->post('/b/{board}/thread', [$threadController, 'store']);
            $router->get('/t/{thread}', [$threadController, 'show']);
            $router->post('/t/{thread}/reply', [$postController, 'store']);
            $router->post('/t/{thread}/lock', [$moderationController, 'lockThread']);
            $router->post('/t/{thread}/unlock', [$moderationController, 'unlockThread']);
            $router->post('/t/{thread}/sticky', [$moderationController, 'stickyThread']);
            $router->post('/t/{thread}/unsticky', [$moderationController, 'unstickyThread']);
            $router->post('/t/{thread}/move', [$moderationController, 'moveThread']);
            $router->get('/p/{post}/edit', [$moderationController, 'editPost']);
            $router->post('/p/{post}/delete', [$moderationController, 'deletePost']);
            $router->post('/p/{post}/edit', [$moderationController, 'editPost']);
            $router->get('/admin/bans', [$moderationController, 'listBans']);
            $router->post('/admin/bans', [$moderationController, 'createBan']);
            $router->post('/admin/bans/{ban}/delete', [$moderationController, 'deleteBan']);
            $router->get('/search', [$searchController, 'search']);
            $router->group('/admin', function (Router $router) use ($adminController) {
                $router->get('/structure', [$adminController, 'structure']);
                $router->post('/categories', [$adminController, 'createCategory']);
                $router->post('/categories/{category}', [$adminController, 'updateCategory']);
                $router->post('/categories/{category}/delete', [$adminController, 'deleteCategory']);
                $router->post('/boards', [$adminController, 'createBoard']);
                $router->post('/boards/{board}', [$adminController, 'updateBoard']);
                $router->post('/boards/{board}/delete', [$adminController, 'deleteBoard']);
                $router->post('/moderators', [$adminController, 'addModerator']);
                $router->post('/moderators/{user}/delete', [$adminController, 'removeModerator']);
            });
        });

        $seed = $this->seedForumData(
            $pdo,
            $communityRepository,
            $categoryRepository,
            $boardRepository,
            $userRepository,
            $roleRepository,
            $threadRepository,
            $postRepository,
            $banRepository,
            $communityModeratorRepository
        );

        return [$router, $seed];
    }

    private function seedForumData(
        \PDO $pdo,
        CommunityRepository $communities,
        CategoryRepository $categories,
        BoardRepository $boards,
        UserRepository $users,
        RoleRepository $roles,
        ThreadRepository $threads,
        PostRepository $posts,
        \Fred\Infrastructure\Database\BanRepository $bans,
        CommunityModeratorRepository $communityModerators
    ): array {
        $roles->ensureDefaultRoles();
        $memberRole = $roles->findBySlug('member');
        $this->assertNotNull($memberRole);
        $moderatorRole = $roles->findBySlug('moderator');
        $this->assertNotNull($moderatorRole);
        $adminRole = $roles->findBySlug('admin');
        $this->assertNotNull($adminRole);

        $memberUser = $users->create('member', 'Member User', password_hash('secret', PASSWORD_BCRYPT), $memberRole->id, time());
        $moderatorUser = $users->create('moderator', 'Moderator User', password_hash('secret', PASSWORD_BCRYPT), $moderatorRole->id, time());
        $adminUser = $users->create('admin', 'Admin User', password_hash('secret', PASSWORD_BCRYPT), $adminRole->id, time());

        $community = $communities->create('main', 'Main Plaza', 'A cozy square', null, time());
        $communityModerators->assign($community->id, $moderatorUser->id, time());

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
            authorId: $memberUser->id,
            isSticky: false,
            isLocked: false,
            isAnnouncement: false,
            timestamp: time(),
        );

        $post = $posts->create(
            communityId: $community->id,
            threadId: $thread->id,
            authorId: $memberUser->id,
            bodyRaw: 'First message',
            bodyParsed: null,
            signatureSnapshot: null,
            timestamp: time(),
        );

        $bannedUser = $users->create('banned', 'Banned User', '', $memberRole->id, time());
        $bans->create(
            userId: $bannedUser->id,
            reason: 'naughty',
            expiresAt: time() + 3600,
            timestamp: time()
        );
        $statement = $pdo->query('SELECT * FROM bans ORDER BY id DESC LIMIT 1');
        $ban = (object) $statement->fetch(\PDO::FETCH_ASSOC);

        return [
            'community_slug' => $community->slug,
            'board_slug' => $board->slug,
            'thread_id' => $thread->id,
            'post_id' => $post->id,
            'member_username' => $memberUser->username,
            'ban_id' => $ban->id,
            'category_id' => $category->id,
            'guest_id' => null,
            'member_id' => $memberUser->id,
            'moderator_id' => $moderatorUser->id,
            'admin_id' => $adminUser->id,
        ];
    }
}
