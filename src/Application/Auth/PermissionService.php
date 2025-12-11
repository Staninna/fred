<?php

declare(strict_types=1);

namespace Fred\Application\Auth;

use Fred\Domain\Auth\RoleSlug;

use function in_array;

final readonly class PermissionService
{
    private const string PERMISSION_THREAD_CREATE = 'thread.create';
    private const string PERMISSION_POST_CREATE = 'post.create';
    private const string PERMISSION_THREAD_LOCK = 'thread.lock';
    private const string PERMISSION_THREAD_STICKY = 'thread.sticky';
    private const string PERMISSION_THREAD_MOVE = 'thread.move';
    private const string PERMISSION_POST_EDIT_ANY = 'post.edit_any';
    private const string PERMISSION_POST_DELETE_ANY = 'post.delete_any';
    private const string PERMISSION_USER_BAN = 'user.ban';

    public function canModerate(CurrentUser $user, ?int $communityId = null): bool
    {
        foreach ([
            self::PERMISSION_THREAD_LOCK,
            self::PERMISSION_THREAD_STICKY,
            self::PERMISSION_THREAD_MOVE,
            self::PERMISSION_POST_EDIT_ANY,
            self::PERMISSION_POST_DELETE_ANY,
            self::PERMISSION_USER_BAN,
        ] as $permission) {
            if ($this->hasForCommunity($user, $permission, $communityId)) {
                return true;
            }
        }

        return false;
    }

    public function canCreateThread(CurrentUser $user): bool
    {
        return $this->has($user, self::PERMISSION_THREAD_CREATE);
    }

    public function canCreateCommunity(CurrentUser $user): bool
    {
        return $user->role === RoleSlug::ADMIN;
    }

    public function canReply(CurrentUser $user): bool
    {
        return $this->has($user, self::PERMISSION_POST_CREATE);
    }

    public function canLockThread(CurrentUser $user, ?int $communityId = null): bool
    {
        return $this->hasForCommunity($user, self::PERMISSION_THREAD_LOCK, $communityId);
    }

    public function canStickyThread(CurrentUser $user, ?int $communityId = null): bool
    {
        return $this->hasForCommunity($user, self::PERMISSION_THREAD_STICKY, $communityId);
    }

    public function canBan(CurrentUser $user, ?int $communityId = null): bool
    {
        return $this->hasForCommunity($user, self::PERMISSION_USER_BAN, $communityId);
    }

    public function canMoveThread(CurrentUser $user, ?int $communityId = null): bool
    {
        return $this->hasForCommunity($user, self::PERMISSION_THREAD_MOVE, $communityId);
    }

    public function canEditAnyPost(CurrentUser $user, ?int $communityId = null): bool
    {
        return $this->hasForCommunity($user, self::PERMISSION_POST_EDIT_ANY, $communityId);
    }

    public function canDeleteAnyPost(CurrentUser $user, ?int $communityId = null): bool
    {
        return $this->hasForCommunity($user, self::PERMISSION_POST_DELETE_ANY, $communityId);
    }

    // Thin wrapper to centralize permission checks.
    private function has(CurrentUser $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }

    private function hasForCommunity(CurrentUser $user, string $permission, ?int $communityId): bool
    {
        if (!$this->has($user, $permission)) {
            return false;
        }

        if ($user->role === RoleSlug::ADMIN) {
            return true;
        }

        if ($user->role === RoleSlug::MODERATOR) {
            if (in_array($permission, [self::PERMISSION_THREAD_CREATE, self::PERMISSION_POST_CREATE], true)) {
                return true;
            }

            if ($communityId === null) {
                return false;
            }

            return $user->isModeratorOf($communityId);
        }

        // Member/guest have no scoped permissions beyond create/reply.
        return false;
    }
}
