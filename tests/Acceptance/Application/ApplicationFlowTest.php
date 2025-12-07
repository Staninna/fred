<?php

declare(strict_types=1);

namespace Tests\Acceptance\Application;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Application\Content\BbcodeParser;
use Fred\Application\Content\UploadService;
use Fred\Application\Search\SearchService;
use Fred\Http\Controller\AdminController;
use Fred\Http\Controller\AuthController;
use Fred\Http\Controller\BoardController;
use Fred\Http\Controller\CommunityController;
use Fred\Http\Controller\CommunityHelper;
use Fred\Http\Controller\ModerationController;
use Fred\Http\Controller\PostController;
use Fred\Http\Controller\ProfileController;
use Fred\Http\Controller\SearchController;
use Fred\Http\Controller\ThreadController;
use Fred\Http\Controller\UploadController;
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
use Fred\Infrastructure\Database\PermissionRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\ReportRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\View\ViewRenderer;
use Tests\TestCase;

final class ApplicationFlowTest extends TestCase
{
    public function testGuestFlowAndGuards(): void
    {
        session_start();
        $app = $this->buildApp();

        $router = $app['router'];
        $context = $app['context'];

        $home = $router->dispatch(new Request(
            method: 'GET',
            path: '/',
            query: [],
            body: [],
        ));
        $this->assertSame(200, $home->status);
        $this->assertStringContainsString($context['community']->name, $home->body);

        $threadResponse = $router->dispatch(new Request(
            method: 'GET',
            path: '/c/' . $context['community']->slug . '/t/' . $context['thread']->id,
            query: [],
            body: [],
        ));
        $this->assertSame(200, $threadResponse->status);
        $this->assertStringContainsString($context['post_body'], $threadResponse->body);

        $createPage = $router->dispatch(new Request(
            method: 'GET',
            path: '/c/' . $context['community']->slug . '/b/' . $context['board']->slug . '/thread/new',
            query: [],
            body: [],
        ));
        $this->assertSame(302, $createPage->status);
        $this->assertSame('/login', $createPage->headers['Location'] ?? null);

        $reply = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $context['community']->slug . '/t/' . $context['thread']->id . '/reply',
            query: [],
            body: ['body' => 'Guest attempt'],
        ));
        $this->assertSame('/login', $reply->headers['Location'] ?? null);

        $adminPage = $router->dispatch(new Request(
            method: 'GET',
            path: '/c/' . $context['community']->slug . '/admin/structure',
            query: [],
            body: [],
        ));
        $this->assertSame('/login', $adminPage->headers['Location'] ?? null);
    }

    public function testMemberRegistrationLoginAndPostingFlow(): void
    {
        session_start();
        $app = $this->buildApp();

        $router = $app['router'];
        $context = $app['context'];
        $repos = $app['repos'];

        $register = $router->dispatch(new Request(
            method: 'POST',
            path: '/register',
            query: [],
            body: [
                'username' => 'newmember',
                'display_name' => 'New Member',
                'password' => 'secret1',
                'password_confirmation' => 'secret1',
            ],
        ));
        $this->assertSame(302, $register->status);
        $this->assertSame('/', $register->headers['Location'] ?? null);

        $logout = $router->dispatch(new Request(
            method: 'POST',
            path: '/logout',
            query: [],
            body: [],
        ));
        $this->assertSame(302, $logout->status);

        $login = $router->dispatch(new Request(
            method: 'POST',
            path: '/login',
            query: [],
            body: [
                'username' => 'newmember',
                'password' => 'secret1',
            ],
        ));
        $this->assertSame(302, $login->status);
        $this->assertSame('/', $login->headers['Location'] ?? null);

        $user = $repos['users']->findByUsername('newmember');
        $this->assertNotNull($user);

        $threadResponse = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $context['community']->slug . '/b/' . $context['board']->slug . '/thread',
            query: [],
            body: [
                'title' => 'Member thread',
                'body' => 'Hello as member',
            ],
        ));
        $this->assertSame(302, $threadResponse->status);
        $threadLocation = $threadResponse->headers['Location'] ?? '';
        $this->assertNotSame('', $threadLocation);

        preg_match('~/t/(\\d+)~', $threadLocation, $threadMatches);
        $this->assertArrayHasKey(1, $threadMatches);
        $threadId = (int) $threadMatches[1];
        $thread = $repos['threads']->findById($threadId);
        $this->assertNotNull($thread);
        $this->assertSame($user->id, $thread->authorId);

        $reply = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $context['community']->slug . '/t/' . $threadId . '/reply',
            query: [],
            body: ['body' => 'Member reply'],
        ));
        $this->assertSame(302, $reply->status);
        $replyLocation = $reply->headers['Location'] ?? '';
        $this->assertStringContainsString('#post-', $replyLocation);

        preg_match('~#post-(\\d+)~', $replyLocation, $postMatches);
        $postId = (int) ($postMatches[1] ?? 0);
        $this->assertGreaterThan(0, $postId);
        $post = $repos['posts']->findById($postId);
        $this->assertNotNull($post);
        $this->assertSame($user->id, $post->authorId);

        $lock = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $context['community']->slug . '/t/' . $threadId . '/lock',
            query: [],
            body: [],
        ));
        $this->assertSame(403, $lock->status);
    }

    public function testModeratorCanModerateContent(): void
    {
        session_start();
        $app = $this->buildApp();

        $_SESSION['user_id'] = $app['context']['users']['moderator']->id;
        $router = $app['router'];
        $context = $app['context'];
        $repos = $app['repos'];

        $threadId = $context['thread']->id;
        $communitySlug = $context['community']->slug;

        $lock = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/t/' . $threadId . '/lock',
            query: [],
            body: [],
        ));
        $this->assertSame(302, $lock->status);
        $this->assertTrue($repos['threads']->findById($threadId)?->isLocked);

        $unlock = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/t/' . $threadId . '/unlock',
            query: [],
            body: [],
        ));
        $this->assertSame(302, $unlock->status);
        $this->assertFalse($repos['threads']->findById($threadId)?->isLocked);

        $move = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/t/' . $threadId . '/move',
            query: [],
            body: ['target_board' => $context['second_board']->slug],
        ));
        $this->assertSame(302, $move->status);
        $this->assertSame($context['second_board']->id, $repos['threads']->findById($threadId)?->boardId);

        $edit = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/p/' . $context['post']->id . '/edit',
            query: [],
            body: ['body' => 'Updated by moderator'],
        ));
        $this->assertSame(302, $edit->status);
        $this->assertSame('Updated by moderator', $repos['posts']->findById($context['post']->id)?->bodyRaw);

        $delete = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/p/' . $context['post']->id . '/delete',
            query: [],
            body: [],
        ));
        $this->assertSame(302, $delete->status);
        $this->assertNull($repos['posts']->findById($context['post']->id));

        $memberRole = $repos['roles']->findBySlug('member');
        $this->assertNotNull($memberRole);
        $userToBan = $repos['users']->create(
            'visitor',
            'Visitor',
            password_hash('secret', PASSWORD_BCRYPT),
            $memberRole->id,
            time(),
        );

        $banResponse = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/admin/bans',
            query: [],
            body: [
                'username' => $userToBan->username,
                'reason' => 'Spam',
                'expires_at' => '',
            ],
        ));
        $this->assertSame(302, $banResponse->status);
        $bans = $repos['bans']->listAll();
        $this->assertNotEmpty(array_filter($bans, static fn (array $ban): bool => (int) $ban['user_id'] === $userToBan->id));
    }

    public function testAdminCanManageCommunitiesBoardsAndModerators(): void
    {
        session_start();
        $app = $this->buildApp();

        $_SESSION['user_id'] = $app['context']['users']['admin']->id;
        $router = $app['router'];
        $repos = $app['repos'];
        $memberUser = $app['context']['users']['member'];

        $createCommunity = $router->dispatch(new Request(
            method: 'POST',
            path: '/communities',
            query: [],
            body: [
                'name' => 'Side Plaza',
                'slug' => 'side',
                'description' => 'Second square',
            ],
        ));
        $this->assertSame(302, $createCommunity->status);
        $this->assertSame('/c/side', $createCommunity->headers['Location'] ?? null);

        $newCommunity = $repos['communities']->findBySlug('side');
        $this->assertNotNull($newCommunity);

        $categoryResponse = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $newCommunity->slug . '/admin/categories',
            query: [],
            body: [
                'name' => 'Updates',
                'position' => 1,
            ],
        ));
        $this->assertSame(302, $categoryResponse->status);
        $categories = $repos['categories']->listByCommunityId($newCommunity->id);
        $this->assertNotEmpty($categories);
        $category = $categories[0];

        $boardResponse = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $newCommunity->slug . '/admin/boards',
            query: [],
            body: [
                'category_id' => $category->id,
                'name' => 'Announcements',
                'slug' => 'announcements',
                'description' => 'News and updates',
                'position' => 1,
            ],
        ));
        $this->assertSame(302, $boardResponse->status);
        $board = $repos['boards']->findBySlug($newCommunity->id, 'announcements');
        $this->assertNotNull($board);

        $promote = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $newCommunity->slug . '/admin/moderators',
            query: [],
            body: ['username' => $memberUser->username],
        ));
        $this->assertSame(302, $promote->status);
        $updatedMember = $repos['users']->findById($memberUser->id);
        $this->assertSame('moderator', $updatedMember?->roleSlug);
        $this->assertTrue($repos['communityModerators']->isModerator($newCommunity->id, $memberUser->id));
    }

    /**
     * @return array{
     *     router: Router,
     *     auth: AuthService,
     *     context: array<string, mixed>,
     *     repos: array<string, object>
     * }
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

        $roleRepository = new RoleRepository($pdo);
        $permissionRepository = new PermissionRepository($pdo);
        $roleRepository->ensureDefaultRoles();
        $permissionRepository->ensureDefaultPermissions();

        $communityRepository = new CommunityRepository($pdo);
        $categoryRepository = new CategoryRepository($pdo);
        $boardRepository = new BoardRepository($pdo);
        $threadRepository = new ThreadRepository($pdo);
        $postRepository = new PostRepository($pdo);
        $profileRepository = new ProfileRepository($pdo);
        $userRepository = new UserRepository($pdo);
        $communityModeratorRepository = new CommunityModeratorRepository($pdo);
        $banRepository = new BanRepository($pdo);
        $attachmentRepository = new AttachmentRepository($pdo);
        $reportRepository = new ReportRepository($pdo);
        $uploadService = new UploadService($config);
        $authService = new AuthService(
            users: $userRepository,
            roles: $roleRepository,
            bans: $banRepository,
        );
        $permissionService = new PermissionService($permissionRepository, $communityModeratorRepository);
        $communityHelper = new CommunityHelper($communityRepository, $categoryRepository, $boardRepository);
        $searchService = new SearchService($pdo);

        $router = new Router($this->basePath('public'));

        $authController = new AuthController($view, $config, $authService, $communityHelper);
        $communityController = new CommunityController($view, $config, $authService, $permissionService, $communityHelper, $communityRepository);
        $adminController = new AdminController($view, $config, $authService, $permissionService, $communityHelper, $categoryRepository, $boardRepository, $communityRepository, $communityModeratorRepository, $userRepository, $roleRepository, $reportRepository);
        $boardController = new BoardController($view, $config, $authService, $permissionService, $communityHelper, $categoryRepository, $threadRepository);
        $threadController = new ThreadController($view, $config, $authService, $permissionService, $communityHelper, $categoryRepository, $threadRepository, $postRepository, new BbcodeParser(), $profileRepository, $uploadService, $attachmentRepository);
        $postController = new PostController($authService, $view, $config, $communityHelper, $threadRepository, $postRepository, new BbcodeParser(), $profileRepository, $permissionService, $uploadService, $attachmentRepository);
        $moderationController = new ModerationController($view, $config, $authService, $permissionService, $communityHelper, $threadRepository, $postRepository, new BbcodeParser(), $userRepository, $banRepository, $boardRepository, $categoryRepository, $reportRepository);
        $profileController = new ProfileController($view, $config, $authService, $communityHelper, $userRepository, $profileRepository, new BbcodeParser(), $uploadService);
        $searchController = new SearchController($view, $config, $authService, $permissionService, $communityHelper, $searchService, $userRepository);
        $uploadController = new UploadController($config);

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
            $router->post('/p/{post}/report', [$moderationController, 'reportPost'], [$authRequired]);

            $router->get('/admin/bans', [$moderationController, 'listBans'], [$authRequired]);
            $router->post('/admin/bans', [$moderationController, 'createBan'], [$authRequired]);
            $router->post('/admin/bans/{ban}/delete', [$moderationController, 'deleteBan'], [$authRequired]);
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
            }, [$authRequired]);
        });

        $seed = $this->seedData(
            communityRepository: $communityRepository,
            categoryRepository: $categoryRepository,
            boardRepository: $boardRepository,
            userRepository: $userRepository,
            roleRepository: $roleRepository,
            threadRepository: $threadRepository,
            postRepository: $postRepository,
            communityModeratorRepository: $communityModeratorRepository,
        );

        return [
            'router' => $router,
            'auth' => $authService,
            'context' => $seed,
            'repos' => [
                'users' => $userRepository,
                'threads' => $threadRepository,
                'posts' => $postRepository,
                'boards' => $boardRepository,
                'categories' => $categoryRepository,
                'communities' => $communityRepository,
                'bans' => $banRepository,
                'communityModerators' => $communityModeratorRepository,
                'roles' => $roleRepository,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seedData(
        CommunityRepository $communityRepository,
        CategoryRepository $categoryRepository,
        BoardRepository $boardRepository,
        UserRepository $userRepository,
        RoleRepository $roleRepository,
        ThreadRepository $threadRepository,
        PostRepository $postRepository,
        CommunityModeratorRepository $communityModeratorRepository,
    ): array {
        $roleRepository->ensureDefaultRoles();
        $memberRole = $roleRepository->findBySlug('member');
        $moderatorRole = $roleRepository->findBySlug('moderator');
        $adminRole = $roleRepository->findBySlug('admin');
        $this->assertNotNull($memberRole);
        $this->assertNotNull($moderatorRole);
        $this->assertNotNull($adminRole);

        $memberUser = $userRepository->create('member', 'Member User', password_hash('secret', PASSWORD_BCRYPT), $memberRole->id, time());
        $moderatorUser = $userRepository->create('moderator', 'Moderator User', password_hash('secret', PASSWORD_BCRYPT), $moderatorRole->id, time());
        $adminUser = $userRepository->create('admin', 'Admin User', password_hash('secret', PASSWORD_BCRYPT), $adminRole->id, time());

        $community = $communityRepository->create('main', 'Main Plaza', 'A cozy square', null, time());
        $communityModeratorRepository->assign($community->id, $moderatorUser->id, time());

        $category = $categoryRepository->create($community->id, 'Lobby', 1, time());
        $board = $boardRepository->create(
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
        $secondBoard = $boardRepository->create(
            communityId: $community->id,
            categoryId: $category->id,
            slug: 'news',
            name: 'News',
            description: 'Announcements',
            position: 2,
            isLocked: false,
            customCss: null,
            timestamp: time(),
        );

        $thread = $threadRepository->create(
            communityId: $community->id,
            boardId: $board->id,
            title: 'Welcome aboard',
            authorId: $memberUser->id,
            isSticky: false,
            isLocked: false,
            isAnnouncement: false,
            timestamp: time(),
        );

        $post = $postRepository->create(
            communityId: $community->id,
            threadId: $thread->id,
            authorId: $memberUser->id,
            bodyRaw: 'First message',
            bodyParsed: null,
            signatureSnapshot: null,
            timestamp: time(),
        );

        return [
            'community' => $community,
            'board' => $board,
            'second_board' => $secondBoard,
            'thread' => $thread,
            'post' => $post,
            'post_body' => 'First message',
            'users' => [
                'member' => $memberUser,
                'moderator' => $moderatorUser,
                'admin' => $adminUser,
            ],
        ];
    }
}
