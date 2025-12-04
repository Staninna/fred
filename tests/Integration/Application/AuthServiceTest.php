<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use Fred\Application\Auth\AuthService;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\UserRepository;
use Tests\TestCase;

final class AuthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
    }

    public function testRegisterCreatesUserAndLogsIn(): void
    {
        $pdo = $this->makeMigratedPdo();
        $auth = new AuthService(
            users: new UserRepository($pdo),
            roles: new RoleRepository($pdo),
        );

        $current = $auth->register('bob', 'Bobby', 'secret123');

        $this->assertTrue($current->authenticated);
        $this->assertSame('bob', $current->username);
        $this->assertSame('member', $current->role);
        $this->assertNotNull($_SESSION['user_id'] ?? null);
    }

    public function testLoginAndLogout(): void
    {
        $pdo = $this->makeMigratedPdo();
        $auth = new AuthService(
            users: new UserRepository($pdo),
            roles: new RoleRepository($pdo),
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
        $pdo = $this->makeMigratedPdo();
        $auth = new AuthService(
            users: new UserRepository($pdo),
            roles: new RoleRepository($pdo),
        );

        $auth->register('mallory', 'Mallory', 'topsecret');
        $auth->logout();
        $_SESSION = [];

        $this->assertFalse($auth->login('mallory', 'wrong'));
        $this->assertTrue($auth->currentUser()->isGuest());
    }
}
