<?php

declare(strict_types=1);

namespace Fred\Domain\Forum;

final readonly class Thread
{
    public function __construct(
        public int $id,
        public int $communityId,
        public int $boardId,
        public string $title,
        public int $authorId,
        public string $authorName,
        public bool $isSticky,
        public bool $isLocked,
        public bool $isAnnouncement,
        public int $createdAt,
        public int $updatedAt,
    ) {
    }
}
