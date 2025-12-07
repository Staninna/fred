<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use Fred\Application\Auth\PermissionService;
use Fred\Application\Auth\CurrentUser;
use Fred\Infrastructure\Database\CommunityModeratorRepository;
use Fred\Infrastructure\Database\PermissionRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\UserRepository;
use Tests\TestCase;

final class PermissionServiceTest extends TestCase
{
    private \PDO $pdo;
    private RoleRepository $roles;
    private PermissionRepository $permissions;
    private CommunityModeratorRepository $communityModerators;
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->makeMigratedPdo();
        $this->roles = new RoleRepository($this->pdo);
        $this->permissions = new PermissionRepository($this->pdo);
        $this->communityModerators = new CommunityModeratorRepository($this->pdo);
        $this->users = new UserRepository($this->pdo);
        $this->roles->ensureDefaultRoles();
        $this->permissions->ensureDefaultPermissions();
    }

    private function user(string $roleSlug, ?int $id = 1): CurrentUser
    {
        return new CurrentUser(
            id: $id,
            username: $roleSlug,
            displayName: ucfirst($roleSlug),
            role: $roleSlug,
            roleName: ucfirst($roleSlug),
            authenticated: true,
        );
    }

    public function testAdminHasGlobalModeration(): void
    {
        $service = new PermissionService($this->permissions, $this->communityModerators);
        $admin = $this->user('admin');

        $this->assertTrue($service->canModerate($admin, 1));
        $this->assertTrue($service->canBan($admin, 1));
        $this->assertTrue($service->canMoveThread($admin, 1));
    }

    public function testModeratorNeedsAssignmentPerCommunity(): void
    {
        $service = new PermissionService($this->permissions, $this->communityModerators);
        $moderator = $this->user('moderator', 10);

        $this->assertFalse($service->canModerate($moderator, 1));

        $this->communityModerators->assign(1, 10, time());
        $this->assertTrue($service->canModerate($moderator, 1));
        $this->assertTrue($service->canLockThread($moderator, 1));
        $this->assertFalse($service->canModerate($moderator, 2));
    }

    public function testMemberCannotModerate(): void
    {
        $service = new PermissionService($this->permissions, $this->communityModerators);
        $member = $this->user('member');

        $this->assertFalse($service->canModerate($member, 1));
        $this->assertFalse($service->canBan($member, 1));
    }
}
