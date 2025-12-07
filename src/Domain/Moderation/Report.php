<?php

declare(strict_types=1);

namespace Fred\Domain\Moderation;

final readonly class Report
{
    public function __construct(
        public int $id,
        public int $communityId,
        public int $postId,
        public int $reporterId,
        public string $reason,
        public string $status,
        public int $createdAt,
        public int $updatedAt,
    ) {
    }
}
