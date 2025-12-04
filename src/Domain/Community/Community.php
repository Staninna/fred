<?php

declare(strict_types=1);

namespace Fred\Domain\Community;

final readonly class Community
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public string $description,
        public ?string $customCss,
        public int $createdAt,
        public int $updatedAt,
    ) {
    }
}
