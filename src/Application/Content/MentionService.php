<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use Fred\Infrastructure\Database\MentionNotificationRepository;
use Fred\Infrastructure\Database\UserRepository;

final readonly class MentionService
{
    public function __construct(
        private UserRepository $users,
        private MentionNotificationRepository $mentions,
    ) {
    }

    public function notifyFromText(int $communityId, int $postId, int $authorId, string $bodyRaw): void
    {
        $handles = $this->extractHandles($bodyRaw);
        if ($handles === []) {
            return;
        }

        $mentionedUsers = $this->users->findByUsernames($handles);
        if ($mentionedUsers === []) {
            return;
        }

        $now = time();
        foreach ($mentionedUsers as $user) {
            if ($user->id === $authorId) {
                continue;
            }

            $this->mentions->create(
                communityId: $communityId,
                postId: $postId,
                mentionedUserId: $user->id,
                mentionedByUserId: $authorId,
                createdAt: $now,
            );
        }
    }

    /**
     * Batch insert notifications for seeding performance
     * @param array<array{communityId: int, postId: int, mentionedUserId: int, mentionedByUserId: int, createdAt: int}> $notifications
     */
    public function batchInsertNotifications(array $notifications): void
    {
        $this->mentions->batchInsert($notifications);
    }

    /**
     * @return string[]
     */
    public function extractHandles(string $bodyRaw): array
    {
        preg_match_all('/(?<=^|[\s(\[>])@([A-Za-z0-9_.-]{3,32})/', $bodyRaw, $matches);

        $normalized = [];
        foreach ($matches[1] ?? [] as $rawHandle) {
            $trimmed = rtrim($rawHandle, '.,;:!');
            if ($trimmed === '') {
                continue;
            }
            $normalized[] = strtolower($trimmed);
        }

        return array_values(array_unique($normalized));
    }
}
