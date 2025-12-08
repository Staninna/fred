<?php

declare(strict_types=1);

namespace Fred\Application\Auth;

use Fred\Infrastructure\Database\PermissionRepository;
use Fred\Infrastructure\Database\CommunityModeratorRepository;

final class PermissionService
{
    private const PERMISSION_THREAD_CREATE = 'thread.create';
    private const PERMISSION_POST_CREATE = 'post.create';
    private const PERMISSION_THREAD_LOCK = 'thread.lock';
    private const PERMISSION_THREAD_STICKY = 'thread.sticky';
    private const PERMISSION_THREAD_MOVE = 'thread.move';
    private const PERMISSION_POST_EDIT_ANY = 'post.edit_any';
    private const PERMISSION_POST_DELETE_ANY = 'post.delete_any';
    private const PERMISSION_USER_BAN = 'user.ban';

    /** @var array<string, bool> Cache of role-wide permission checks (role|perm => bool) */
    private array $rolePermissionCache = [];

    /** @var array<string, bool> Cache of scoped permission checks (role|user|perm|community => bool) */
    private array $scopedPermissionCache = [];

    public function __construct(
        private readonly PermissionRepository $permissions,
        private readonly CommunityModeratorRepository $communityModerators,
    ) {
    }

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
        return $user->role === 'admin';
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

    private function has(CurrentUser $user, string $permission): bool
    {
        $role = $user->role;
        if ($role === '') {
            return false;
        }

        $key = $role . '|' . $permission;
        if (isset($this->rolePermissionCache[$key])) {
            return $this->rolePermissionCache[$key];
        }

        return $this->rolePermissionCache[$key] = $this->permissions->roleHasPermission($role, $permission);
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
            if (\in_array($permission, [self::PERMISSION_THREAD_CREATE, self::PERMISSION_POST_CREATE], true)) {
                return true;
            }

            if ($communityId === null || $user->id === null) {
                return false;
            }

            $key = $user->role . '|' . ($user->id ?? 'null') . '|' . $permission . '|' . $communityId;
            if (isset($this->scopedPermissionCache[$key])) {
                return $this->scopedPermissionCache[$key];
            }

            $allowed = $this->communityModerators->isModerator($communityId, $user->id);
            if ($allowed) {
                $this->scopedPermissionCache[$key] = true;
            }

            return $allowed;
        }

        // Member/guest have no scoped permissions beyond create/reply.
        return false;
    }
}
