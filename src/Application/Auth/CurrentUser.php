<?php

declare(strict_types=1);

namespace Fred\Application\Auth;

final readonly class CurrentUser
{
    public function __construct(
        public ?int   $id,
        public string $username,
        public string $displayName,
        public string $role,
        public string $roleName,
        public bool   $authenticated,
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
