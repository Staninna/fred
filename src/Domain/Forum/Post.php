<?php

declare(strict_types=1);

namespace Fred\Domain\Forum;

final readonly class Post
{
    public function __construct(
        public int $id,
        public int $communityId,
        public int $threadId,
        public int $authorId,
        public string $authorName,
        public string $authorUsername,
        public string $bodyRaw,
        public ?string $bodyParsed,
        public ?string $signatureSnapshot,
        public int $createdAt,
        public int $updatedAt,
    ) {
    }
}
