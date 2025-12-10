<?php

declare(strict_types=1);

namespace Fred\Application\Auth;

use Fred\Domain\Auth\User;
use Fred\Infrastructure\Database\BanRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\UserRepository;
use InvalidArgumentException;

use function password_hash;
use function password_verify;

use RuntimeException;

use function session_regenerate_id;
use function time;
use function trim;

final class AuthService
{
    private const string SESSION_KEY = 'user_id';
    private const string SESSION_PERMISSIONS_KEY = 'user_permissions';
    private const string SESSION_MODERATED_COMMUNITIES_KEY = 'user_moderated_communities';

    private ?CurrentUser $cached = null;

    public function __construct(
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly BanRepository $bans,
    ) {
    }

    public function currentUser(): CurrentUser
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $userId = $this->sessionGet(self::SESSION_KEY);

        if ($userId === null) {
            return $this->cached = $this->guest();
        }

        $user = $this->users->findById((int) $userId);

        if ($user === null) {
            unset($_SESSION[self::SESSION_KEY]);

            return $this->cached = $this->guest();
        }

        if ($this->bans->isBanned($user->id, $this->now())) {
            $this->logout();
            $this->cached = null;

            return $this->guest();
        }

        $permissions = $this->sessionGet(self::SESSION_PERMISSIONS_KEY) ?? [];
        $moderatedCommunities = $this->sessionGet(self::SESSION_MODERATED_COMMUNITIES_KEY) ?? [];

        return $this->cached = $this->mapUser($user, $permissions, $moderatedCommunities);
    }

    public function register(string $username, string $displayName, string $password): CurrentUser
    {
        $username = trim($username);
        $displayName = trim($displayName) === '' ? $username : trim($displayName);

        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }

        if ($this->users->findByUsername($username) !== null) {
            throw new RuntimeException('Username is already taken.');
        }

        $role = $this->roles->findBySlug('member');

        if ($role === null) {
            throw new RuntimeException('Member role is missing.');
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $user = $this->users->create(
            username: $username,
            displayName: $displayName,
            passwordHash: $passwordHash,
            roleId: $role->id,
            createdAt: $this->now(),
        );

        return $this->loginUser($user);
    }

    public function login(string $username, string $password): bool
    {
        $user = $this->users->findByUsername(trim($username));

        if ($user === null) {
            return false;
        }

        if (!password_verify($password, $user->passwordHash)) {
            return false;
        }

        if ($this->bans->isBanned($user->id, $this->now())) {
            return false;
        }

        $this->loginUser($user);

        return true;
    }

    public function logout(): void
    {
        $this->sessionForget(self::SESSION_KEY);
        $this->cached = null;
        $this->regenerateSession();
    }

    public function flushPermissionCache(?int $userId = null): void
    {
        if ($userId === null) {
            $currentUserId = $this->sessionGet(self::SESSION_KEY);
            $userId = $currentUserId !== null ? (int) $currentUserId : null;
        }

        if ($userId === null) {
            return;
        }

        $user = $this->users->findById($userId);

        if ($user === null) {
            return;
        }

        $this->cachePermissions($user);

        if ($this->cached !== null && $this->cached->id === $userId) {
            $this->cached = null;
        }
    }

    private function guest(): CurrentUser
    {
        $guestRole = $this->roles->findBySlug('guest');

        return new CurrentUser(
            id: null,
            username: 'guest',
            displayName: 'Guest',
            role: $guestRole?->slug ?? 'guest',
            roleName: $guestRole?->name ?? 'Guest',
            authenticated: false,
            permissions: [],
            moderatedCommunities: [],
        );
    }

    /**
     * @param array<string> $permissions
     * @param array<int> $moderatedCommunities
     */
    private function mapUser(User $user, array $permissions = [], array $moderatedCommunities = []): CurrentUser
    {
        return new CurrentUser(
            id: $user->id,
            username: $user->username,
            displayName: $user->displayName,
            role: $user->roleSlug,
            roleName: $user->roleName,
            authenticated: true,
            permissions: $permissions,
            moderatedCommunities: $moderatedCommunities,
        );
    }

    private function loginUser(User $user): CurrentUser
    {
        $this->sessionSet(self::SESSION_KEY, $user->id);
        $this->cachePermissions($user);
        $this->regenerateSession();

        $permissions = $this->sessionGet(self::SESSION_PERMISSIONS_KEY) ?? [];
        $moderatedCommunities = $this->sessionGet(self::SESSION_MODERATED_COMMUNITIES_KEY) ?? [];

        $current = $this->mapUser($user, $permissions, $moderatedCommunities);
        $this->cached = $current;

        return $current;
    }

    private function cachePermissions(User $user): void
    {
        $permissions = $this->roles->getPermissionsForRole($user->roleSlug);
        $this->sessionSet(self::SESSION_PERMISSIONS_KEY, $permissions);

        $moderatedCommunities = [];
        if ($user->roleSlug === 'moderator' && $user->id !== null) {
            $moderatedCommunities = $this->users->getModeratedCommunityIds($user->id);
        }
        $this->sessionSet(self::SESSION_MODERATED_COMMUNITIES_KEY, $moderatedCommunities);
    }

    private function sessionGet(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    private function sessionSet(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    private function sessionForget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    private function regenerateSession(): void
    {
        session_regenerate_id(true);
    }

    private function now(): int
    {
        return time();
    }
}
