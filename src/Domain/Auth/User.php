<?php

declare(strict_types=1);

namespace Fred\Domain\Auth;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $displayName,
        public readonly string $passwordHash,
        public readonly int $roleId,
        public readonly string $roleSlug,
        public readonly string $roleName,
        public readonly int $createdAt,
    ) {
    }
}
