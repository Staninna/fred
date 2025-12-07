<?php

declare(strict_types=1);

namespace Fred\Application\Auth;

use Fred\Infrastructure\Database\PermissionRepository;
use Fred\Infrastructure\Database\CommunityModeratorRepository;

final readonly class PermissionService
{
    public function __construct(
        private PermissionRepository $permissions,
        private CommunityModeratorRepository $communityModerators,
    ) {
    }

    public function canModerate(CurrentUser $user, ?int $communityId = null): bool
    {
        return $this->canLockThread($user, $communityId)
            || $this->canStickyThread($user, $communityId)
            || $this->canMoveThread($user, $communityId)
            || $this->canEditAnyPost($user, $communityId)
            || $this->canDeleteAnyPost($user, $communityId)
            || $this->canBan($user, $communityId);
    }

    public function canCreateThread(CurrentUser $user): bool
    {
        return $this->has($user, 'thread.create');
    }

    public function canCreateCommunity(CurrentUser $user): bool
    {
        return $user->role === 'admin';
    }

    public function canReply(CurrentUser $user): bool
    {
        return $this->has($user, 'post.create');
    }

    public function canLockThread(CurrentUser $user, ?int $communityId = null): bool
    {
        return $this->hasForCommunity($user, 'thread.lock', $communityId);
    }

    public function canStickyThread(CurrentUser $user, ?int $communityId = null): bool
    {
        return $this->hasForCommunity($user, 'thread.sticky', $communityId);
    }

    public function canBan(CurrentUser $user, ?int $communityId = null): bool
    {
        return $this->hasForCommunity($user, 'user.ban', $communityId);
    }

    public function canMoveThread(CurrentUser $user, ?int $communityId = null): bool
    {
        return $this->hasForCommunity($user, 'thread.move', $communityId);
    }

    public function canEditAnyPost(CurrentUser $user, ?int $communityId = null): bool
    {
        return $this->hasForCommunity($user, 'post.edit_any', $communityId);
    }

    public function canDeleteAnyPost(CurrentUser $user, ?int $communityId = null): bool
    {
        return $this->hasForCommunity($user, 'post.delete_any', $communityId);
    }

    private function has(CurrentUser $user, string $permission): bool
    {
        $role = $user->role;
        if ($role === '') {
            return false;
        }

        return $this->permissions->roleHasPermission($role, $permission);
    }

    private function hasForCommunity(CurrentUser $user, string $permission, ?int $communityId): bool
    {
        if (!$this->has($user, $permission)) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'moderator') {
            if (\in_array($permission, ['thread.create', 'post.create'], true)) {
                return true;
            }

            if ($communityId === null || $user->id === null) {
                return false;
            }

            return $this->communityModerators->isModerator($communityId, $user->id);
        }

        // Member/guest have no scoped permissions beyond create/reply.
        return false;
    }
}
