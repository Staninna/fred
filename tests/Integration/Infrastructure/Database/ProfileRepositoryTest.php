<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Database;

use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\UserRepository;
use Tests\TestCase;

final class ProfileRepositoryTest extends TestCase
{
    public function testCreateAndUpdateSignature(): void
    {
        $pdo = $this->makeMigratedPdo();
        $roles = new RoleRepository($pdo);
        $roles->ensureDefaultRoles();
        $role = $roles->findBySlug('member');
        $this->assertNotNull($role);

        $communities = new CommunityRepository($pdo);
        $community = $communities->create('test', 'Test', '', null, time());

        $users = new UserRepository($pdo);
        $user = $users->create('profileuser', 'Profile User', 'hash', $role->id, time());

        $profiles = new ProfileRepository($pdo);
        $profile = $profiles->create(
            userId: $user->id,
            communityId: $community->id,
            bio: '',
            location: '',
            website: '',
            signatureRaw: '',
            signatureParsed: '',
            avatarPath: '',
            timestamp: time(),
        );

        $this->assertSame($user->id, $profile->userId);

        $profiles->updateSignature($user->id, $community->id, 'hello', '<b>hello</b>', time());
        $updated = $profiles->findByUserAndCommunity($user->id, $community->id);

        $this->assertNotNull($updated);
        $this->assertSame('hello', $updated->signatureRaw);
        $this->assertSame('<b>hello</b>', $updated->signatureParsed);
    }
}
