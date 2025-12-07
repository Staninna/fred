<?php

declare(strict_types=1);

namespace Fred\Application\Auth;

use Fred\Domain\Auth\User;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\BanRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\PermissionRepository;
use Fred\Infrastructure\Database\CommunityRepository;

use function password_hash;
use function password_verify;
use function session_regenerate_id;
use function time;
use function trim;

final class AuthService
{
    private const string SESSION_KEY = 'user_id';

    private ?CurrentUser $cached = null;

    public function __construct(
        private readonly AppConfig $config,
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly ProfileRepository $profiles,
        private readonly BanRepository $bans,
        private readonly PermissionRepository $permissions,
        private readonly CommunityRepository $communities,
    ) {
        $this->roles->ensureDefaultRoles();
        $this->permissions->ensureDefaultPermissions();
    }

    public function currentUser(): CurrentUser
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        if ($this->allowDevImpersonation()) {
            $devUser = $_GET['dev_user'] ?? null;
            if (\is_string($devUser) && $devUser !== '') {
                $user = $this->users->findByUsername($devUser);
                if ($user !== null && !$this->bans->isBanned($user->id, time())) {
                    return $this->cached = $this->mapUser($user);
                }
            }
        }

        $userId = $_SESSION[self::SESSION_KEY] ?? null;
        if ($userId === null) {
            return $this->cached = $this->guest();
        }

        $user = $this->users->findById((int) $userId);
        if ($user === null) {
            unset($_SESSION[self::SESSION_KEY]);

            return $this->cached = $this->guest();
        }

        if ($this->bans->isBanned($user->id, time())) {
            $this->logout();

            return $this->cached = $this->guest();
        }

        return $this->cached = $this->mapUser($user);
    }

    public function register(string $username, string $displayName, string $password): CurrentUser
    {
        $username = trim($username);
        $displayName = trim($displayName) === '' ? $username : trim($displayName);

        if ($username === '') {
            throw new \InvalidArgumentException('Username is required.');
        }

        if ($this->users->findByUsername($username) !== null) {
            throw new \RuntimeException('Username is already taken.');
        }

        $role = $this->roles->findBySlug('member');
        if ($role === null) {
            throw new \RuntimeException('Member role is missing.');
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $user = $this->users->create(
            username: $username,
            displayName: $displayName,
            passwordHash: $passwordHash,
            roleId: $role->id,
            createdAt: time(),
        );
        $communities = $this->communities->all();
        foreach ($communities as $community) {
            $this->profiles->create(
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
        }

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

        if ($this->bans->isBanned($user->id, time())) {
            return false;
        }

        $this->loginUser($user);

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        $this->cached = null;
        session_regenerate_id(true);
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
        );
    }

    private function mapUser(User $user): CurrentUser
    {
        return new CurrentUser(
            id: $user->id,
            username: $user->username,
            displayName: $user->displayName,
            role: $user->roleSlug,
            roleName: $user->roleName,
            authenticated: true,
        );
    }

    private function loginUser(User $user): CurrentUser
    {
        $_SESSION[self::SESSION_KEY] = $user->id;
        session_regenerate_id(true);

        $current = $this->mapUser($user);
        $this->cached = $current;

        return $current;
    }

    private function allowDevImpersonation(): bool
    {
        return $this->config->environment !== 'production';
    }
}
