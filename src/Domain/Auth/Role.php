<?php

declare(strict_types=1);

namespace Fred\Domain\Auth;

final readonly class Role
{
    public function __construct(
        public int    $id,
        public string $slug,
        public string $name,
    ) {
    }
}
