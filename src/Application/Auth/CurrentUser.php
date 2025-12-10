<?php

declare(strict_types=1);

namespace Fred\Application\Auth;

use function in_array;

final readonly class CurrentUser
{
    /**
     * @param array<string> $permissions
     * @param array<int> $moderatedCommunities
     */
    public function __construct(
        public ?int   $id,
        public string $username,
        public string $displayName,
        public string $role,
        public string $roleName,
        public bool   $authenticated,
        public array  $permissions = [],
        public array  $moderatedCommunities = [],
    ) {
    }

    public function isGuest(): bool
    {
        return !$this->authenticated;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function isModeratorOf(int $communityId): bool
    {
        return in_array($communityId, $this->moderatedCommunities, true);
    }
}
