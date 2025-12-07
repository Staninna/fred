<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use Fred\Application\Auth\AuthService;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BanRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\Database\PermissionRepository;
use Tests\TestCase;

final class AuthServiceTest extends TestCase
{
    private UserRepository $userRepository;
    private RoleRepository $roleRepository;
    private ProfileRepository $profileRepository;
    private BanRepository $banRepository;
    private PermissionRepository $permissionRepository;
    private AppConfig $appConfig;
    private \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        $this->pdo = $this->makeMigratedPdo();
        $this->userRepository = new UserRepository($this->pdo);
        $this->roleRepository = new RoleRepository($this->pdo);
        $this->profileRepository = new ProfileRepository($this->pdo);
        $this->banRepository = new BanRepository($this->pdo);
        $this->permissionRepository = new PermissionRepository($this->pdo);
        $this->appConfig = new AppConfig(
            environment: 'testing',
            baseUrl: 'http://localhost',
            databasePath: ':memory:',
            uploadsPath: $this->createTempDir('fred-uploads-'),
            logsPath: $this->createTempDir('fred-logs-'),
            basePath: $this->basePath(),
        );
    }

    public function testRegisterCreatesUserAndLogsIn(): void
    {
        $auth = new AuthService(
            config: $this->appConfig,
            users: $this->userRepository,
            roles: $this->roleRepository,
            profiles: $this->profileRepository,
            bans: $this->banRepository,
            permissions: $this->permissionRepository,
        );

        $current = $auth->register('bob', 'Bobby', 'secret123');

        $this->assertTrue($current->authenticated);
        $this->assertSame('bob', $current->username);
        $this->assertSame('member', $current->role);
        $this->assertNotNull($_SESSION['user_id'] ?? null);

        $profile = (new ProfileRepository($this->pdo))->findByUserId($current->id ?? 0);
        $this->assertNotNull($profile);
    }

    public function testLoginAndLogout(): void
    {
        $auth = new AuthService(
            config: $this->appConfig,
            users: $this->userRepository,
            roles: $this->roleRepository,
            profiles: $this->profileRepository,
            bans: $this->banRepository,
            permissions: $this->permissionRepository,
        );

        $auth->register('jane', 'Jane', 'password1');
        $_SESSION = [];

        $this->assertTrue($auth->login('jane', 'password1'));
        $this->assertTrue($auth->currentUser()->isAuthenticated());

        $auth->logout();
        $this->assertFalse($auth->currentUser()->isAuthenticated());
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $auth = new AuthService(
            config: $this->appConfig,
            users: $this->userRepository,
            roles: $this->roleRepository,
            profiles: $this->profileRepository,
            bans: $this->banRepository,
            permissions: $this->permissionRepository,
        );

        $auth->register('mallory', 'Mallory', 'topsecret');
        $auth->logout();
        $_SESSION = [];

        $this->assertFalse($auth->login('mallory', 'wrong'));
        $this->assertTrue($auth->currentUser()->isGuest());
    }

    public function testBannedUserCannotLogin(): void
    {
        $auth = new AuthService(
            config: $this->appConfig,
            users: $this->userRepository,
            roles: $this->roleRepository,
            profiles: $this->profileRepository,
            bans: $this->banRepository,
            permissions: $this->permissionRepository,
        );

        $auth->register('banned', 'Banned User', 'secret');
        $auth->logout();
        $_SESSION = [];

        $user = $this->userRepository->findByUsername('banned');
        $this->bans->create($user->id, 'Because', null, time());

        $this->assertFalse($auth->login('banned', 'secret'));
        $this->assertTrue($auth->currentUser()->isGuest());
    }
}
