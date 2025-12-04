<?php

declare(strict_types=1);

namespace Fred\Domain\Community;

final readonly class Board
{
    public function __construct(
        public int $id,
        public int $communityId,
        public int $categoryId,
        public string $name,
        public string $description,
        public int $position,
        public bool $isLocked,
        public ?string $customCss,
        public int $createdAt,
        public int $updatedAt,
    ) {
    }
}
