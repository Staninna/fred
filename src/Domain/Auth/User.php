<?php

declare(strict_types=1);

namespace Fred\Domain\Auth;

final readonly class User
{
    public function __construct(
        public int    $id,
        public string $username,
        public string $displayName,
        public string $passwordHash,
        public int    $roleId,
        public string $roleSlug,
        public string $roleName,
        public int    $createdAt,
    ) {
    }
}
