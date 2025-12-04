<?php

declare(strict_types=1);

namespace Fred\Application\Auth;

final class CurrentUser
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $username,
        public readonly string $displayName,
        public readonly string $role,
        public readonly string $roleName,
        public readonly bool $authenticated,
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
}
