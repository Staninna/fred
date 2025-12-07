<?php

declare(strict_types=1);

namespace Fred\Domain\Forum;

final readonly class Attachment
{
    public function __construct(
        public int $id,
        public int $communityId,
        public int $postId,
        public int $userId,
        public string $path,
        public string $originalName,
        public string $mimeType,
        public int $sizeBytes,
        public int $createdAt,
    ) {
    }
}
