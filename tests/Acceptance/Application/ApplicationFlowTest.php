<?php

declare(strict_types=1);

namespace Tests\Acceptance\Application;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Application\Content\BbcodeParser;
use Fred\Application\Content\EmoticonSet;
use Fred\Application\Content\LinkPreviewer;
use Fred\Application\Content\MentionService;
use Fred\Application\Content\UploadService;
use Fred\Application\Security\CsrfGuard;
use Fred\Application\Search\SearchService;
use Fred\Http\Controller\AdminController;
use Fred\Http\Controller\AuthController;
use Fred\Http\Controller\BoardController;
use Fred\Http\Controller\CommunityController;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Controller\MentionController;
use Fred\Http\Controller\ModerationController;
use Fred\Http\Controller\PostController;
use Fred\Http\Controller\ReactionController;
use Fred\Http\Controller\ProfileController;
use Fred\Http\Controller\SearchController;
use Fred\Http\Controller\ThreadController;
use Fred\Http\Controller\UploadController;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Http\Routing\Router;
use Fred\Http\Middleware\ResolveBoardMiddleware;
use Fred\Http\Middleware\ResolveCommunityMiddleware;
use Fred\Http\Middleware\ResolvePostMiddleware;
use Fred\Http\Middleware\ResolveThreadMiddleware;
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
use Fred\Infrastructure\Database\ReactionRepository;
use Fred\Infrastructure\Database\MentionNotificationRepository;
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
        $token = $app['csrfToken'];

        $home = $this->dispatch($app, new Request(
            method: 'GET',
            path: '/',
            query: [],
            body: [],
        ));
        $this->assertSame(200, $home->status);
        $this->assertStringContainsString($context['community']->name, $home->body);

        $threadResponse = $this->dispatch($app, new Request(
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
            body: ['body' => 'Guest attempt', '_token' => $token],
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
        $token = $app['csrfToken'];
        $emoticonCode = $app['emoticons']->codes()[0] ?? '001';

        $register = $router->dispatch(new Request(
            method: 'POST',
            path: '/register',
            query: [],
            body: [
                'username' => 'newmember',
                'display_name' => 'New Member',
                'password' => 'secret1',
                'password_confirmation' => 'secret1',
                '_token' => $token,
            ],
        ));
        $this->assertSame(302, $register->status);
        $this->assertSame('/', $register->headers['Location'] ?? null);

        $logout = $router->dispatch(new Request(
            method: 'POST',
            path: '/logout',
            query: [],
            body: ['_token' => $token],
        ));
        $this->assertSame(302, $logout->status);

        $login = $router->dispatch(new Request(
            method: 'POST',
            path: '/login',
            query: [],
            body: [
                'username' => 'newmember',
                'password' => 'secret1',
                '_token' => $token,
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
                '_token' => $token,
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
            body: ['body' => 'Member reply', '_token' => $token],
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
            body: ['_token' => $token],
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
        $token = $app['csrfToken'];

        $threadId = $context['thread']->id;
        $communitySlug = $context['community']->slug;

        $lock = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/t/' . $threadId . '/lock',
            query: [],
            body: ['_token' => $token],
        ));
        $this->assertSame(302, $lock->status);
        $this->assertTrue($repos['threads']->findById($threadId)?->isLocked);

        $unlock = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/t/' . $threadId . '/unlock',
            query: [],
            body: ['_token' => $token],
        ));
        $this->assertSame(302, $unlock->status);
        $this->assertFalse($repos['threads']->findById($threadId)?->isLocked);

        $move = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/t/' . $threadId . '/move',
            query: [],
            body: ['target_board' => $context['second_board']->slug, '_token' => $token],
        ));
        $this->assertSame(302, $move->status);
        $this->assertSame($context['second_board']->id, $repos['threads']->findById($threadId)?->boardId);

        $edit = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/p/' . $context['post']->id . '/edit',
            query: [],
            body: ['body' => 'Updated by moderator', '_token' => $token],
        ));
        $this->assertSame(302, $edit->status);
        $this->assertSame('Updated by moderator', $repos['posts']->findById($context['post']->id)?->bodyRaw);

        $delete = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/p/' . $context['post']->id . '/delete',
            query: [],
            body: ['_token' => $token],
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
                '_token' => $token,
            ],
        ));
        $this->assertSame(302, $banResponse->status);
        $bans = $repos['bans']->listAll();
        $this->assertNotEmpty(array_filter($bans, static fn (array $ban): bool => (int) $ban['user_id'] === $userToBan->id));
    }

    public function testContentReactionsLinkPreviewsAndMentionsFlow(): void
    {
        session_start();
        $app = $this->buildApp();

        $router = $app['router'];
        $context = $app['context'];
        $repos = $app['repos'];
        $token = $app['csrfToken'];
        $emoticonCode = $app['emoticons']->codes()[0] ?? '001';

        $communitySlug = $context['community']->slug;
        $threadId = $context['thread']->id;

        $previewUrl = 'https://example.com/article';
        $previewCachePath = $this->basePath('storage/link_previews/' . sha1($previewUrl) . '.json');
        @mkdir((string) dirname($previewCachePath), 0775, true);
        $previewWritten = file_put_contents(
            $previewCachePath,
            (string) json_encode([
                'url' => $previewUrl,
                'title' => 'Example Article',
                'description' => 'A concise preview description.',
                'image' => null,
                'host' => 'example.com',
            ], JSON_PRETTY_PRINT),
        );
        $this->assertNotFalse($previewWritten);

        $login = $router->dispatch(new Request(
            method: 'POST',
            path: '/login',
            query: [],
            body: [
                'username' => 'member',
                'password' => 'secret',
                '_token' => $token,
            ],
        ));
        $this->assertSame(302, $login->status);

        $body = 'Check this out ' . $previewUrl . ' and hello @moderator!';
        $reply = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/t/' . $threadId . '/reply',
            query: [],
            body: ['body' => $body, '_token' => $token],
        ));
        $this->assertSame(302, $reply->status);
        $replyLocation = $reply->headers['Location'] ?? '';
        $this->assertStringContainsString('#post-', $replyLocation);
        preg_match('~#post-(\d+)~', $replyLocation, $postMatches);
        $postId = (int) ($postMatches[1] ?? 0);
        $this->assertGreaterThan(0, $postId);
        $post = $repos['posts']->findById($postId);
        $this->assertNotNull($post);
        $this->assertStringContainsString('@moderator', $post->bodyRaw ?? '');

        $allMentions = $repos['mentions']->listForUser($context['users']['moderator']->id, $context['community']->id);
        $this->assertCount(1, $allMentions, 'Expected 1 mention for moderator; got ' . count($allMentions));
        $this->assertSame($postId, $allMentions[0]->postId);

        $react = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/p/' . $postId . '/react',
            query: [],
            body: ['emoticon' => $emoticonCode, '_token' => $token],
        ));
        $this->assertSame(302, $react->status);
        $reactions = $repos['reactions']->listByPostIds([$postId]);
        $this->assertSame(1, $reactions[$postId][$emoticonCode] ?? 0);
        $this->assertSame($emoticonCode, $repos['reactions']->findUserReaction($postId, $context['users']['member']->id));

        $removeReaction = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/p/' . $postId . '/react',
            query: [],
            body: ['remove' => '1', '_token' => $token],
        ));
        $this->assertSame(302, $removeReaction->status);
        $this->assertSame([], $repos['reactions']->listByPostIds([$postId]));

        $threadPage = $this->dispatch($app, new Request(
            method: 'GET',
            path: '/c/' . $communitySlug . '/t/' . $threadId,
            query: [],
            body: [],
        ));
        $this->assertSame(200, $threadPage->status);
        $this->assertStringContainsString('Example Article', $threadPage->body);
        $this->assertStringContainsString('example.com', $threadPage->body);

        $_SESSION['user_id'] = $context['users']['moderator']->id;
        $mentionInbox = $this->dispatchWithRefreshedAuth($app, new Request(
            method: 'GET',
            path: '/c/' . $communitySlug . '/mentions',
            query: [],
            body: [],
        ));
        $this->assertSame(200, $mentionInbox->status);
        $this->assertStringContainsString('Unread: 1', $mentionInbox->body);
        $this->assertStringContainsString((string) $postId, $mentionInbox->body);

        $markRead = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $communitySlug . '/mentions/' . $allMentions[0]->id . '/read',
            query: [],
            body: ['_token' => $token],
        ));
        $this->assertSame(302, $markRead->status);
        $this->assertSame(0, $repos['mentions']->countUnread($context['users']['moderator']->id, $context['community']->id));
    }

    public function testAdminCanManageCommunitiesBoardsAndModerators(): void
    {
        session_start();
        $app = $this->buildApp();

        $_SESSION['user_id'] = $app['context']['users']['admin']->id;
        $router = $app['router'];
        $repos = $app['repos'];
        $memberUser = $app['context']['users']['member'];
        $token = $app['csrfToken'];

        $createCommunity = $router->dispatch(new Request(
            method: 'POST',
            path: '/communities',
            query: [],
            body: [
                'name' => 'Side Plaza',
                'slug' => 'side',
                'description' => 'Second square',
                '_token' => $token,
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
                '_token' => $token,
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
                '_token' => $token,
            ],
        ));
        $this->assertSame(302, $boardResponse->status);
        $board = $repos['boards']->findBySlug($newCommunity->id, 'announcements');
        $this->assertNotNull($board);

        $promote = $router->dispatch(new Request(
            method: 'POST',
            path: '/c/' . $newCommunity->slug . '/admin/moderators',
            query: [],
            body: ['username' => $memberUser->username, '_token' => $token],
        ));
        $this->assertSame(302, $promote->status);
        $updatedMember = $repos['users']->findById($memberUser->id);
        $this->assertSame('moderator', $updatedMember?->roleSlug);
        $this->assertTrue($repos['communityModerators']->isModerator($newCommunity->id, $memberUser->id));
    }

    public function testMentionExtractionAndNotification(): void
    {
        $pdo = $this->makeMigratedPdo();
        $userRepository = new UserRepository($pdo);
        $roleRepository = new RoleRepository($pdo);
        $communityRepository = new CommunityRepository($pdo);
        $categoryRepository = new CategoryRepository($pdo);
        $boardRepository = new BoardRepository($pdo);
        $threadRepository = new ThreadRepository($pdo);
        $postRepository = new PostRepository($pdo);
        $mentionNotificationRepository = new MentionNotificationRepository($pdo);

        $roleRepository->ensureDefaultRoles();
        $memberRole = $roleRepository->findBySlug('member');
        $this->assertNotNull($memberRole);

        $user1 = $userRepository->create('alice', 'Alice', password_hash('secret', PASSWORD_BCRYPT), $memberRole->id, time());
        $user2 = $userRepository->create('bobsmith', 'Bob', password_hash('secret', PASSWORD_BCRYPT), $memberRole->id, time());

        $community = $communityRepository->create('test', 'Test', 'Test community', null, time());
        $category = $categoryRepository->create($community->id, 'Test Cat', 1, time());
        $board = $boardRepository->create($community->id, $category->id, 'testboard', 'Test Board', 'desc', 1, false, null, time());
        $thread = $threadRepository->create($community->id, $board->id, 'Test Thread', $user1->id, false, false, false, time());
        $post = $postRepository->create($community->id, $thread->id, $user1->id, 'Hey @alice and @bobsmith check this', 'parsed', null, time());

        $mentionService = new MentionService($userRepository, $mentionNotificationRepository);
        $handles = $mentionService->extractHandles('Hey @alice and @bobsmith check this out');
        $this->assertSame(['alice', 'bobsmith'], $handles);

        $mentionService->notifyFromText(
            communityId: $community->id,
            postId: $post->id,
            authorId: $user1->id,
            bodyRaw: 'Hey @alice and @bobsmith check this',
        );

        $this->assertSame(1, $mentionNotificationRepository->countForUser($user2->id, $community->id));
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
        $reactionRepository = new ReactionRepository($pdo);
        $mentionNotificationRepository = new MentionNotificationRepository($pdo);
        $mentionService = new MentionService($userRepository, $mentionNotificationRepository);
        $emoticonSet = new EmoticonSet($config);
        $linkPreviewer = new LinkPreviewer($config);
        $uploadService = new UploadService($config);
        $authService = new AuthService(
            users: $userRepository,
            roles: $roleRepository,
            bans: $banRepository,
        );
        $permissionService = new PermissionService($permissionRepository, $communityModeratorRepository);
        $communityContext = new \Fred\Http\Navigation\CommunityContext($communityRepository, $categoryRepository, $boardRepository);
        $searchService = new SearchService($pdo);
        $csrfGuard = new CsrfGuard();
        $csrfToken = $csrfGuard->token();

        $router = new Router($this->basePath('public'));

        $csrfProtect = static function (Request $request, callable $next) use ($csrfGuard): Response {
            if ($request->method === 'POST' && !$csrfGuard->isValid($request)) {
                return new Response(419, ['Content-Type' => 'text/plain; charset=utf-8'], 'CSRF token mismatch');
            }

            return $next($request);
        };

        $communityContextMiddleware = new ResolveCommunityMiddleware($communityContext, $view);
        $boardContext = new ResolveBoardMiddleware($communityContext, $categoryRepository, $view);
        $threadContext = new ResolveThreadMiddleware($boardRepository, $threadRepository, $categoryRepository, $view);
        $postContext = new ResolvePostMiddleware($postRepository, $threadRepository, $boardRepository, $categoryRepository, $view);

        $authController = new AuthController($view, $config, $authService);
        $communityController = new CommunityController($view, $config, $authService, $communityContext, $permissionService, $communityRepository, $categoryRepository, $boardRepository);
        $adminController = new AdminController($view, $config, $authService, $permissionService, $communityContext, $categoryRepository, $boardRepository, $communityRepository, $communityModeratorRepository, $userRepository, $roleRepository, $reportRepository);
        $boardController = new BoardController($view, $config, $authService, $communityContext, $permissionService, $boardRepository, $categoryRepository, $threadRepository);
        $threadController = new ThreadController(
            $view,
            $config,
            $authService,
            $permissionService,
            $communityContext,
            $categoryRepository,
            $boardRepository,
            $threadRepository,
            $postRepository,
            new BbcodeParser(),
            $linkPreviewer,
            $userRepository,
            $profileRepository,
            $uploadService,
            $attachmentRepository,
            $reactionRepository,
            $mentionNotificationRepository,
            $emoticonSet,
            $mentionService,
            $pdo
        );
        $postController = new PostController($authService, $view, $config, $threadRepository, $postRepository, new BbcodeParser(), $profileRepository, $permissionService, $uploadService, $attachmentRepository, $mentionService);
        $moderationController = new ModerationController($view, $config, $authService, $permissionService, $communityContext, $threadRepository, $postRepository, new BbcodeParser(), $userRepository, $banRepository, $boardRepository, $categoryRepository, $reportRepository, $attachmentRepository, $uploadService, $mentionService);
        $profileController = new ProfileController($view, $config, $authService, $userRepository, $profileRepository, new BbcodeParser(), $uploadService);
        $searchController = new SearchController($view, $config, $authService, $permissionService, $communityContext, $searchService, $boardRepository, $categoryRepository, $userRepository);
        $uploadController = new UploadController($config);
        $reactionController = new ReactionController($authService, $config, $view, $threadRepository, $postRepository, $reactionRepository, $emoticonSet);
        $mentionController = new MentionController($authService, $config, $view, $communityContext, $mentionNotificationRepository, $userRepository, $boardRepository, $categoryRepository);

        $authRequired = static function (Request $request, callable $next) use ($authService): Response {
            if ($authService->currentUser()->isGuest()) {
                return Response::redirect('/login');
            }

            return $next($request);
        };

        $router->get('/', [$communityController, 'index']);
        $router->post('/communities', [$communityController, 'store'], [$csrfProtect]);
        $router->get('/login', [$authController, 'showLoginForm']);
        $router->post('/login', [$authController, 'login'], [$csrfProtect]);
        $router->get('/register', [$authController, 'showRegisterForm']);
        $router->post('/register', [$authController, 'register'], [$csrfProtect]);
        $router->post('/logout', [$authController, 'logout'], [$csrfProtect]);

        $router->get('/c/{community}', [$communityController, 'show'], [$communityContextMiddleware]);
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
            $csrfProtect,
            $communityContextMiddleware,
            $boardContext,
            $threadContext,
            $postContext
        ) {
            $router->get('/', [$communityController, 'show'], [$communityContextMiddleware]);
            $router->get('/about', [$communityController, 'about']);
            $router->get('/u/{username}', [$profileController, 'show']);

            $router->group('/settings', function (Router $router) use ($profileController, $authRequired, $csrfProtect) {
                $router->get('/profile', [$profileController, 'editProfile']);
                $router->post('/profile', [$profileController, 'updateProfile'], [$authRequired, $csrfProtect]);
                $router->get('/signature', [$profileController, 'editSignature']);
                $router->post('/signature', [$profileController, 'updateSignature'], [$authRequired, $csrfProtect]);
                $router->get('/avatar', [$profileController, 'editAvatar']);
                $router->post('/avatar', [$profileController, 'updateAvatar'], [$authRequired, $csrfProtect]);
            }, [$authRequired]);

            $router->get('/b/{board}', [$boardController, 'show'], [$boardContext]);
            $router->group('/b/{board}', function (Router $router) use ($threadController, $authRequired, $csrfProtect, $boardContext) {
                $router->get('/thread/new', [$threadController, 'create']);
                $router->post('/thread', [$threadController, 'store'], [$authRequired, $csrfProtect]);
            }, [$authRequired, $boardContext]);

            $router->get('/t/{thread}', [$threadController, 'show'], [$threadContext]);
            $router->post('/t/{thread}/reply', [$postController, 'store'], [$authRequired, $csrfProtect, $threadContext]);
            $router->post('/t/{thread}/lock', [$moderationController, 'lockThread'], [$authRequired, $csrfProtect, $threadContext]);
            $router->post('/t/{thread}/unlock', [$moderationController, 'unlockThread'], [$authRequired, $csrfProtect, $threadContext]);
            $router->post('/t/{thread}/sticky', [$moderationController, 'stickyThread'], [$authRequired, $csrfProtect, $threadContext]);
            $router->post('/t/{thread}/unsticky', [$moderationController, 'unstickyThread'], [$authRequired, $csrfProtect, $threadContext]);
            $router->post('/t/{thread}/announce', [$moderationController, 'announceThread'], [$authRequired, $csrfProtect, $threadContext]);
            $router->post('/t/{thread}/unannounce', [$moderationController, 'unannounceThread'], [$authRequired, $csrfProtect, $threadContext]);
            $router->post('/t/{thread}/move', [$moderationController, 'moveThread'], [$authRequired, $csrfProtect, $threadContext]);
            $router->get('/p/{post}/edit', [$moderationController, 'editPost'], [$authRequired, $postContext]);
            $router->post('/p/{post}/delete', [$moderationController, 'deletePost'], [$authRequired, $csrfProtect, $postContext]);
            $router->post('/p/{post}/edit', [$moderationController, 'editPost'], [$authRequired, $csrfProtect, $postContext]);
            $router->post('/p/{post}/report', [$moderationController, 'reportPost'], [$authRequired, $csrfProtect, $postContext]);
            $router->post('/p/{post}/react', [$reactionController, 'add'], [$authRequired, $csrfProtect, $postContext]);
            $router->get('/mentions', [$mentionController, 'inbox'], [$authRequired, $communityContextMiddleware]);
            $router->post('/mentions/read', [$mentionController, 'markRead'], [$authRequired, $csrfProtect, $communityContextMiddleware]);
            $router->post('/mentions/{mention}/read', [$mentionController, 'markOneRead'], [$authRequired, $csrfProtect, $communityContextMiddleware]);
            $router->get('/mentions/suggest', [$mentionController, 'suggest'], [$authRequired, $communityContextMiddleware]);

            $router->get('/admin/bans', [$moderationController, 'listBans'], [$authRequired, $communityContextMiddleware]);
            $router->post('/admin/bans', [$moderationController, 'createBan'], [$authRequired, $csrfProtect, $communityContextMiddleware]);
            $router->post('/admin/bans/{ban}/delete', [$moderationController, 'deleteBan'], [$authRequired, $csrfProtect, $communityContextMiddleware]);
            $router->get('/search', [$searchController, 'search']);

            $router->group('/admin', function (Router $router) use ($adminController, $authRequired, $csrfProtect) {
                $router->get('/structure', [$adminController, 'structure']);
                $router->post('/custom-css', [$adminController, 'updateCommunityCss'], [$authRequired, $csrfProtect]);
                $router->post('/categories', [$adminController, 'createCategory'], [$authRequired, $csrfProtect]);
                $router->post('/categories/reorder', [$adminController, 'reorderCategories'], [$authRequired, $csrfProtect]);
                $router->post('/categories/{category}', [$adminController, 'updateCategory'], [$authRequired, $csrfProtect]);
                $router->post('/categories/{category}/delete', [$adminController, 'deleteCategory'], [$authRequired, $csrfProtect]);
                $router->post('/boards', [$adminController, 'createBoard'], [$authRequired, $csrfProtect]);
                $router->post('/boards/reorder', [$adminController, 'reorderBoards'], [$authRequired, $csrfProtect]);
                $router->post('/boards/{board}', [$adminController, 'updateBoard'], [$authRequired, $csrfProtect]);
                $router->post('/boards/{board}/delete', [$adminController, 'deleteBoard'], [$authRequired, $csrfProtect]);
                $router->post('/moderators', [$adminController, 'addModerator'], [$authRequired, $csrfProtect]);
                $router->post('/moderators/{user}/delete', [$adminController, 'removeModerator'], [$authRequired, $csrfProtect]);
                $router->get('/reports', [$adminController, 'reports']);
                $router->post('/reports/{report}/resolve', [$adminController, 'resolveReport'], [$authRequired, $csrfProtect]);
                $router->get('/settings', [$adminController, 'settings']);
                $router->post('/settings', [$adminController, 'updateSettings'], [$authRequired, $csrfProtect]);
                $router->get('/users', [$adminController, 'users']);
            }, [$authRequired]);
        }, [$communityContextMiddleware]);

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
            'view' => $view,
            'config' => $config,
            'context' => $seed,
            'csrfToken' => $csrfToken,
            'emoticons' => $emoticonSet,
            'communityContext' => $communityContext,
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
                'reactions' => $reactionRepository,
                'mentions' => $mentionNotificationRepository,
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

    private function dispatch(array $app, Request $request): Response
    {
        $view = $app['view'];
        $auth = $app['auth'];
        $config = $app['config'];
        $communityContext = $app['communityContext'];

        $view->share('currentUser', $auth->currentUser());
        $view->share('environment', $config->environment);
        $view->share('baseUrl', $config->baseUrl);
        $view->share('activePath', $request->path);
        $view->share('navSections', $communityContext->navSections(null, [], []));
        $view->share('currentCommunity', null);
        $view->share('customCss', '');
        $view->share('csrfToken', $app['csrfToken']);

        return $app['router']->dispatch($request);
    }

    private function dispatchWithRefreshedAuth(array $app, Request $request): Response
    {
        $auth = $app['auth'];
        $reflection = new \ReflectionClass($auth);
        $cachedProperty = $reflection->getProperty('cached');
        $cachedProperty->setAccessible(true);
        $cachedProperty->setValue($auth, null);

        return $this->dispatch($app, $request);
    }
}
