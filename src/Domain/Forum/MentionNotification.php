<?php

declare(strict_types=1);

namespace Fred\Domain\Forum;

final readonly class MentionNotification
{
    public function __construct(
        public int $id,
        public int $communityId,
        public string $communitySlug,
        public int $postId,
        public int $threadId,
        public string $threadTitle,
        public int $mentionedUserId,
        public int $mentionedByUserId,
        public string $mentionedByName,
        public string $mentionedByUsername,
        public string $postBodyRaw,
        public int $postPosition,
        public int $createdAt,
        public ?int $readAt,
    ) {
    }
}
