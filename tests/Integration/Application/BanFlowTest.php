<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use Fred\Application\Auth\AuthService;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BanRepository;
use Fred\Infrastructure\Database\PermissionRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\UserRepository;
use Tests\TestCase;

final class BanFlowTest extends TestCase
{
    private \PDO $pdo;
    private AppConfig $config;
    private UserRepository $users;
    private RoleRepository $roles;
    private ProfileRepository $profiles;
    private BanRepository $bans;
    private PermissionRepository $permissions;

    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];

        $this->pdo = $this->makeMigratedPdo();
        $this->config = new AppConfig(
            environment: 'testing',
            baseUrl: 'http://localhost',
            databasePath: ':memory:',
            uploadsPath: $this->createTempDir('uploads-'),
            logsPath: $this->createTempDir('logs-'),
            basePath: $this->basePath(),
        );
        $this->users = new UserRepository($this->pdo);
        $this->roles = new RoleRepository($this->pdo);
        $this->profiles = new ProfileRepository($this->pdo);
        $this->bans = new BanRepository($this->pdo);
        $this->permissions = new PermissionRepository($this->pdo);
        $this->roles->ensureDefaultRoles();
        $this->permissions->ensureDefaultPermissions();
    }

    public function testBannedUserCannotPostAfterBan(): void
    {
        $auth = new AuthService(
            config: $this->config,
            users: $this->users,
            roles: $this->roles,
            profiles: $this->profiles,
            bans: $this->bans,
            permissions: $this->permissions,
        );

        $current = $auth->register('banme', 'Ban Me', 'secret');
        $auth->logout();
        $_SESSION = [];
        $user = $this->users->findByUsername('banme');
        $this->bans->create($user->id, 'violation', null, time());

        $this->assertFalse($auth->login('banme', 'secret'));
        $this->assertTrue($auth->currentUser()->isGuest());
    }
}
