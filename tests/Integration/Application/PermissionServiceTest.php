<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use Fred\Application\Auth\CurrentUser;
use Fred\Application\Auth\PermissionService;
use Fred\Domain\Auth\User;
use Fred\Infrastructure\Database\CommunityModeratorRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\PermissionRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\UserRepository;
use RuntimeException;
use Tests\TestCase;

final class PermissionServiceTest extends TestCase
{
    private RoleRepository $roles;
    private PermissionRepository $permissions;
    private CommunityModeratorRepository $communityModerators;
    private CommunityRepository $communities;
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = $this->makeMigratedPdo();
        $this->roles = new RoleRepository($pdo);
        $this->permissions = new PermissionRepository($pdo);
        $this->communityModerators = new CommunityModeratorRepository($pdo);
        $this->users = new UserRepository($pdo);
        $this->communities = new CommunityRepository($pdo);
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
        $community = $this->communities->create('demo', 'Demo', 'Demo community', null, time());
        $moderatorUser = $this->createUserWithRole('moderator');
        $moderator = $this->user('moderator', $moderatorUser->id);

        $this->assertFalse($service->canModerate($moderator, $community->id));

        $this->communityModerators->assign($community->id, $moderatorUser->id, time());
        $this->assertTrue($service->canModerate($moderator, $community->id));
        $this->assertTrue($service->canLockThread($moderator, $community->id));
        $this->assertFalse($service->canModerate($moderator, $community->id + 1));
    }

    public function testMemberCannotModerate(): void
    {
        $service = new PermissionService($this->permissions, $this->communityModerators);
        $member = $this->user('member');

        $this->assertFalse($service->canModerate($member, 1));
        $this->assertFalse($service->canBan($member, 1));
    }

    private function createUserWithRole(string $roleSlug): User
    {
        $role = $this->roles->findBySlug($roleSlug);

        if ($role === null) {
            throw new RuntimeException('Role not found: ' . $roleSlug);
        }

        return $this->users->create(
            username: $roleSlug . '_user',
            displayName: ucfirst($roleSlug) . ' User',
            passwordHash: password_hash('password', PASSWORD_BCRYPT),
            roleId: $role->id,
            createdAt: time(),
        );
    }
}
