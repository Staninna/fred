<?php

declare(strict_types=1);

namespace Fred\Domain\Auth;

final class Role
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
    ) {
    }
}
