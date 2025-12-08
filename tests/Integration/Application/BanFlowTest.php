<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use Fred\Application\Auth\AuthService;
use Fred\Infrastructure\Database\BanRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\UserRepository;
use Tests\TestCase;

final class BanFlowTest extends TestCase
{
    private \PDO $pdo;
    private UserRepository $users;
    private RoleRepository $roles;
    private BanRepository $bans;

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];

        $this->pdo = $this->makeMigratedPdo();
        $this->users = new UserRepository($this->pdo);
        $this->roles = new RoleRepository($this->pdo);
        $this->bans = new BanRepository($this->pdo);
        $this->roles->ensureDefaultRoles();
    }

    public function testBannedUserCannotPostAfterBan(): void
    {
        $auth = new AuthService(
            users: $this->users,
            roles: $this->roles,
            bans: $this->bans,
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
