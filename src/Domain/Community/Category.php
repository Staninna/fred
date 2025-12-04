<?php

declare(strict_types=1);

namespace Fred\Domain\Community;

final readonly class Category
{
    public function __construct(
        public int $id,
        public int $communityId,
        public string $name,
        public int $position,
        public int $createdAt,
        public int $updatedAt,
    ) {
    }
}
