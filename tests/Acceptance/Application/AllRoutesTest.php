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
use Fred\Application\Content\PostReferenceValidator;
use Fred\Application\Content\ThreadStateService;
use Fred\Application\Content\UploadService;
use Fred\Application\Search\SearchService;
use Fred\Http\Controller\AdminController;
use Fred\Http\Controller\AuthController;
use Fred\Http\Controller\BoardController;
use Fred\Http\Controller\CommunityController;
use Fred\Http\Controller\ModerationController;
use Fred\Http\Controller\PostController;
use Fred\Http\Controller\ProfileController;
use Fred\Http\Controller\SearchController;
use Fred\Http\Controller\ThreadController;
use Fred\Http\Middleware\ResolveBoardMiddleware;
use Fred\Http\Middleware\ResolveCommunityMiddleware;
use Fred\Http\Middleware\ResolvePostMiddleware;
use Fred\Http\Middleware\ResolveThreadMiddleware;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
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
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AllRoutesTest extends TestCase
{
    #[DataProvider('roleProvider')]
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

            $view = $context['view'];
            $auth = $context['auth'];
            $config = $context['config'];
            $communityContext = $context['communityContext'];

            $view->share('currentUser', $auth->currentUser());
            $view->share('environment', $config->environment);
            $view->share('baseUrl', $config->baseUrl);
            $view->share('activePath', $path);
            $view->share('navSections', $communityContext->navSections(null, [], []));
            $view->share('currentCommunity', null);
            $view->share('customCss', '');

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

    /**
     * @return array<string, array{0: string}>
     */
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
        $banRepository = new BanRepository($pdo);
        $attachmentRepository = new AttachmentRepository($pdo);
        $uploadService = new UploadService($config);
        $authService = new AuthService(
            users: $userRepository,
            roles: $roleRepository,
            bans: $banRepository,
        );
        $permissionService = new PermissionService();
        $communityContext = new CommunityContext($communityRepository, $categoryRepository, $boardRepository);
        $searchService = new SearchService($pdo);

        $router = new Router($this->basePath('public'));

        $communityContextMiddleware = new ResolveCommunityMiddleware($communityContext, $view, $config);
        $boardContext = new ResolveBoardMiddleware($communityContext, $categoryRepository, $view, $config);
        $threadContext = new ResolveThreadMiddleware($boardRepository, $threadRepository, $categoryRepository, $view, $config);
        $postContext = new ResolvePostMiddleware($postRepository, $threadRepository, $boardRepository, $categoryRepository, $view, $config);

        $authController = new AuthController($view, $authService);
        $communityController = new CommunityController($view, $config, $authService, $communityContext, $permissionService, $communityRepository);
        $reportRepository = new ReportRepository($pdo);
        $adminController = new AdminController($view, $config, $authService, $permissionService, $categoryRepository, $boardRepository, $communityRepository, $communityModeratorRepository, $userRepository, $roleRepository, $reportRepository);
        $boardController = new BoardController($view, $config, $authService, $communityContext, $permissionService, $threadRepository);

        $reactionRepository = new ReactionRepository($pdo);
        $mentionNotificationRepository = new MentionNotificationRepository($pdo);
        $emoticons = new EmoticonSet($config);
        $mentionService = new MentionService($userRepository, $mentionNotificationRepository);
        $createThreadService = new CreateThreadService($permissionService, $threadRepository, $postRepository, new BbcodeParser(), $profileRepository, $uploadService, $attachmentRepository, $mentionService, $pdo);

        $threadController = new ThreadController(
            $view,
            $config,
            $authService,
            $communityContext,
            $permissionService,
            $categoryRepository,
            $boardRepository,
            $postRepository,
            new LinkPreviewer($config),
            $userRepository,
            $profileRepository,
            $attachmentRepository,
            $reactionRepository,
            $mentionNotificationRepository,
            $emoticons,
            $createThreadService,
            new PostReferenceValidator(),
        );

        $createReplyService = new CreateReplyService($permissionService, $postRepository, new BbcodeParser(), $profileRepository, $uploadService, $attachmentRepository, $mentionService);
        $postController = new PostController($view, $config, $authService, $communityContext, $createReplyService);

        $threadStateService = new ThreadStateService($permissionService, $threadRepository);
        $editPostService = new EditPostService($permissionService, $postRepository, new BbcodeParser(), $mentionService);
        $deletePostService = new \Fred\Application\Content\DeletePostService($permissionService, $postRepository, new \Fred\Application\Content\AttachmentCleanupHelper($attachmentRepository, $uploadService, $postRepository));
        $moveThreadService = new \Fred\Application\Content\MoveThreadService($permissionService, $threadRepository, $boardRepository);
        $reportPostService = new \Fred\Application\Content\ReportPostService($reportRepository);
        $createBanService = new \Fred\Application\Moderation\CreateBanService($userRepository, $banRepository);
        $deleteBanService = new \Fred\Application\Moderation\DeleteBanService($banRepository);

        $moderationController = new ModerationController(
            $view,
            $config,
            $authService,
            $communityContext,
            $permissionService,
            $userRepository,
            $banRepository,
            $boardRepository,
            $categoryRepository,
            $threadStateService,
            $editPostService,
            $deletePostService,
            $moveThreadService,
            $reportPostService,
            $createBanService,
            $deleteBanService
        );
        $profileController = new ProfileController($view, $config, $authService, $userRepository, $profileRepository, new BbcodeParser(), $uploadService);
        $searchController = new SearchController($view, $config, $authService, $permissionService, $communityContext, $searchService, $boardRepository, $categoryRepository, $userRepository);

        // This is a partial copy from public/index.php. Ideally, this would be refactored.
        $router->get('/', [$communityController, 'index']);
        $router->post('/communities', [$communityController, 'store']);
        $router->get('/login', [$authController, 'showLoginForm']);
        $router->post('/login', [$authController, 'login']);
        $router->get('/register', [$authController, 'showRegisterForm']);
        $router->post('/register', [$authController, 'register']);
        $router->post('/logout', [$authController, 'logout']);

        $router->get('/c/{community}', [$communityController, 'show'], [$communityContextMiddleware]);

        $router->group('/c/{community}', function (Router $router) use (
            $communityController,
            $boardController,
            $threadController,
            $postController,
            $adminController,
            $profileController,
            $moderationController,
            $searchController,
            $communityContextMiddleware,
            $boardContext,
            $threadContext,
            $postContext
        ) {
            $router->get('/', [$communityController, 'show'], [$communityContextMiddleware]);
            $router->get('/u/{username}', [$profileController, 'show'], [$communityContextMiddleware]);
            $router->get('/settings/profile', [$profileController, 'editProfile'], [$communityContextMiddleware]);
            $router->post('/settings/profile', [$profileController, 'updateProfile'], [$communityContextMiddleware]);
            $router->get('/settings/signature', [$profileController, 'editSignature'], [$communityContextMiddleware]);
            $router->post('/settings/signature', [$profileController, 'updateSignature'], [$communityContextMiddleware]);
            $router->get('/b/{board}', [$boardController, 'show'], [$communityContextMiddleware, $boardContext]);
            $router->get('/b/{board}/thread/new', [$threadController, 'create'], [$communityContextMiddleware, $boardContext]);
            $router->post('/b/{board}/thread', [$threadController, 'store'], [$communityContextMiddleware, $boardContext]);
            $router->get('/t/{thread}', [$threadController, 'show'], [$communityContextMiddleware, $threadContext]);
            $router->post('/t/{thread}/reply', [$postController, 'store'], [$communityContextMiddleware, $threadContext]);
            $router->post('/t/{thread}/lock', [$moderationController, 'lockThread'], [$communityContextMiddleware, $threadContext]);
            $router->post('/t/{thread}/unlock', [$moderationController, 'unlockThread'], [$communityContextMiddleware, $threadContext]);
            $router->post('/t/{thread}/sticky', [$moderationController, 'stickyThread'], [$communityContextMiddleware, $threadContext]);
            $router->post('/t/{thread}/unsticky', [$moderationController, 'unstickyThread'], [$communityContextMiddleware, $threadContext]);
            $router->post('/t/{thread}/move', [$moderationController, 'moveThread'], [$communityContextMiddleware, $threadContext]);
            $router->get('/p/{post}/edit', [$moderationController, 'editPost'], [$communityContextMiddleware, $postContext]);
            $router->post('/p/{post}/delete', [$moderationController, 'deletePost'], [$communityContextMiddleware, $postContext]);
            $router->post('/p/{post}/edit', [$moderationController, 'editPost'], [$communityContextMiddleware, $postContext]);
            $router->get('/admin/bans', [$moderationController, 'listBans'], [$communityContextMiddleware]);
            $router->post('/admin/bans', [$moderationController, 'createBan'], [$communityContextMiddleware]);
            $router->post('/admin/bans/{ban}/delete', [$moderationController, 'deleteBan'], [$communityContextMiddleware]);
            $router->get('/search', [$searchController, 'search'], [$communityContextMiddleware]);
            $router->group('/admin', function (Router $router) use ($adminController, $communityContextMiddleware, $boardContext) {
                $router->get('/structure', [$adminController, 'structure'], [$communityContextMiddleware]);
                $router->post('/categories', [$adminController, 'createCategory'], [$communityContextMiddleware]);
                $router->post('/categories/{category}', [$adminController, 'updateCategory'], [$communityContextMiddleware]);
                $router->post('/categories/{category}/delete', [$adminController, 'deleteCategory'], [$communityContextMiddleware]);
                $router->post('/boards', [$adminController, 'createBoard'], [$communityContextMiddleware]);
                $router->post('/boards/{board}', [$adminController, 'updateBoard'], [$communityContextMiddleware, $boardContext]);
                $router->post('/boards/{board}/delete', [$adminController, 'deleteBoard'], [$communityContextMiddleware, $boardContext]);
                $router->post('/moderators', [$adminController, 'addModerator'], [$communityContextMiddleware]);
                $router->post('/moderators/{user}/delete', [$adminController, 'removeModerator'], [$communityContextMiddleware]);
            });
        }, [$communityContextMiddleware]);

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

        return [$router, $seed + [
            'view' => $view,
            'auth' => $authService,
            'config' => $config,
            'communityContext' => $communityContext,
        ]];
    }

    /**
     * @return array{
     *     community_slug: string,
     *     board_slug: string,
     *     thread_id: int,
     *     post_id: int,
     *     member_username: string,
     *     ban_id: int,
     *     category_id: int,
     *     guest_id: null,
     *     member_id: int,
     *     moderator_id: int,
     *     admin_id: int
     * }
     */
    private function seedForumData(
        PDO $pdo,
        CommunityRepository $communities,
        CategoryRepository $categories,
        BoardRepository $boards,
        UserRepository $users,
        RoleRepository $roles,
        ThreadRepository $threads,
        PostRepository $posts,
        BanRepository $bans,
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
        if ($statement === false) {
            throw new \RuntimeException('Failed to fetch ban');
        }
        $ban = (object) $statement->fetch(PDO::FETCH_ASSOC);

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
