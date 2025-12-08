<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use Fred\Application\Auth\AuthService;
use Fred\Infrastructure\Database\BanRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\UserRepository;
use Tests\TestCase;

final class AuthServiceTest extends TestCase
{
    private UserRepository $userRepository;
    private RoleRepository $roleRepository;
    private ProfileRepository $profileRepository;
    private BanRepository $banRepository;
    private int $communityId;

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        $pdo = $this->makeMigratedPdo();
        $this->userRepository = new UserRepository($pdo);
        $this->roleRepository = new RoleRepository($pdo);
        $this->profileRepository = new ProfileRepository($pdo);
        $this->banRepository = new BanRepository($pdo);
        $communityRepository = new CommunityRepository($pdo);
        $community = $communityRepository->create('test', 'Test Community', '', null, time());
        $this->communityId = $community->id;
    }

    public function testRegisterCreatesUserAndLogsIn(): void
    {
        $auth = new AuthService(
            users: $this->userRepository,
            roles: $this->roleRepository,
            bans: $this->banRepository,
        );

        $current = $auth->register('bob', 'Bobby', 'secret123');

        $this->assertTrue($current->authenticated);
        $this->assertSame('bob', $current->username);
        $this->assertSame('member', $current->role);
        $this->assertNotNull($_SESSION['user_id'] ?? null);

        $this->assertNull($this->profileRepository->findByUserAndCommunity($current->id ?? 0, $this->communityId));

        $profile = $this->profileRepository->ensureExists($current->id ?? 0, $this->communityId);
        $this->assertSame($current->id, $profile->userId);
    }

    public function testLoginAndLogout(): void
    {
        $auth = new AuthService(
            users: $this->userRepository,
            roles: $this->roleRepository,
            bans: $this->banRepository,
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
            users: $this->userRepository,
            roles: $this->roleRepository,
            bans: $this->banRepository,
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
            users: $this->userRepository,
            roles: $this->roleRepository,
            bans: $this->banRepository,
        );

        $auth->register('banned', 'Banned User', 'secret');
        $auth->logout();
        $_SESSION = [];

        $user = $this->userRepository->findByUsername('banned');
        $this->banRepository->create($user->id, 'Because', null, time());

        $this->assertFalse($auth->login('banned', 'secret'));
        $this->assertTrue($auth->currentUser()->isGuest());
    }
}
