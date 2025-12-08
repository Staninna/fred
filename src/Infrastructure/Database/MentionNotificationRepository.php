<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use function count;

use Fred\Domain\Forum\MentionNotification;
use PDO;

final readonly class MentionNotificationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(
        int $communityId,
        int $postId,
        int $mentionedUserId,
        int $mentionedByUserId,
        int $createdAt,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO mention_notifications (community_id, post_id, mentioned_user_id, mentioned_by_user_id, created_at, read_at)
             VALUES (:community_id, :post_id, :mentioned_user_id, :mentioned_by_user_id, :created_at, NULL)
             ON CONFLICT(post_id, mentioned_user_id) DO UPDATE SET
                 mentioned_by_user_id = excluded.mentioned_by_user_id,
                 created_at = excluded.created_at,
                 read_at = NULL'
        );

        $statement->execute([
            'community_id' => $communityId,
            'post_id' => $postId,
            'mentioned_user_id' => $mentionedUserId,
            'mentioned_by_user_id' => $mentionedByUserId,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * Batch insert mention notifications for seeding performance
     * @param array<array{communityId: int, postId: int, mentionedUserId: int, mentionedByUserId: int, createdAt: int}> $mentions
     */
    public function batchInsert(array $mentions): void
    {
        if ($mentions === []) {
            return;
        }

        // Chunk to avoid SQLite's 999 parameter limit (5 params per row, so 199 rows max)
        $chunks = array_chunk($mentions, 199);

        foreach ($chunks as $chunk) {
            $placeholders = [];
            $values = [];

            foreach ($chunk as $mention) {
                $placeholders[] = '(?, ?, ?, ?, ?, NULL)';
                $values[] = $mention['communityId'];
                $values[] = $mention['postId'];
                $values[] = $mention['mentionedUserId'];
                $values[] = $mention['mentionedByUserId'];
                $values[] = $mention['createdAt'];
            }

            $sql = 'INSERT OR IGNORE INTO mention_notifications (community_id, post_id, mentioned_user_id, mentioned_by_user_id, created_at, read_at) VALUES '
                . implode(', ', $placeholders);

            $statement = $this->pdo->prepare($sql);
            $statement->execute($values);
        }
    }

    /** @return MentionNotification[] */
    public function listForUser(int $userId, int $communityId, int $limit = 20, int $offset = 0): array
    {
        $statement = $this->pdo->prepare(
            'SELECT mn.id, mn.community_id, c.slug AS community_slug, mn.post_id, mn.mentioned_user_id, mn.mentioned_by_user_id,
                    mn.created_at, mn.read_at, p.thread_id, p.body_raw, t.title AS thread_title,
                    (SELECT COUNT(*) FROM posts p2 WHERE p2.thread_id = p.thread_id AND p2.created_at <= p.created_at) AS post_position,
                    u.display_name AS author_name, u.username AS author_username
             FROM mention_notifications mn
             JOIN posts p ON p.id = mn.post_id AND p.community_id = mn.community_id
             JOIN threads t ON t.id = p.thread_id
             JOIN users u ON u.id = mn.mentioned_by_user_id
             JOIN communities c ON c.id = mn.community_id
             WHERE mn.mentioned_user_id = :user_id AND mn.community_id = :community_id
             ORDER BY mn.created_at DESC, mn.id DESC
             LIMIT :limit OFFSET :offset'
        );

        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':community_id', $communityId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'hydrate'], $rows);
    }

    public function countForUser(int $userId, int $communityId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM mention_notifications WHERE mentioned_user_id = :user_id AND community_id = :community_id'
        );

        $statement->execute([
            'user_id' => $userId,
            'community_id' => $communityId,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function countUnread(int $userId, int $communityId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM mention_notifications WHERE mentioned_user_id = :user_id AND community_id = :community_id AND read_at IS NULL'
        );

        $statement->execute([
            'user_id' => $userId,
            'community_id' => $communityId,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function markAllRead(int $userId, int $communityId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE mention_notifications SET read_at = :now WHERE mentioned_user_id = :user_id AND community_id = :community_id AND read_at IS NULL'
        );

        $statement->execute([
            'now' => time(),
            'user_id' => $userId,
            'community_id' => $communityId,
        ]);
    }

    public function markOneRead(int $mentionId, int $userId, int $communityId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE mention_notifications SET read_at = :now WHERE id = :id AND mentioned_user_id = :user_id AND community_id = :community_id AND read_at IS NULL'
        );

        $statement->execute([
            'now' => time(),
            'id' => $mentionId,
            'user_id' => $userId,
            'community_id' => $communityId,
        ]);
    }

    /**
     * @param int[] $postIds
     * @return array<int, MentionNotification[]>
     */
    public function listForPosts(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT mn.id, mn.community_id, c.slug AS community_slug, mn.post_id, mn.mentioned_user_id, mn.mentioned_by_user_id,
                    mn.created_at, mn.read_at, p.thread_id, p.body_raw, t.title AS thread_title,
                    u.display_name AS author_name, u.username AS author_username
             FROM mention_notifications mn
             JOIN posts p ON p.id = mn.post_id
             JOIN threads t ON t.id = p.thread_id
             JOIN users u ON u.id = mn.mentioned_by_user_id
             JOIN communities c ON c.id = mn.community_id
             WHERE mn.post_id IN ($placeholders)
             ORDER BY mn.id ASC"
        );
        $statement->execute($postIds);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $grouped = [];

        foreach ($rows as $row) {
            $notification = $this->hydrate($row);
            $grouped[$notification->postId][] = $notification;
        }

        return $grouped;
    }

    private function hydrate(array $row): MentionNotification
    {
        return new MentionNotification(
            id: (int) $row['id'],
            communityId: (int) $row['community_id'],
            communitySlug: (string) $row['community_slug'],
            postId: (int) $row['post_id'],
            threadId: (int) $row['thread_id'],
            threadTitle: (string) $row['thread_title'],
            mentionedUserId: (int) $row['mentioned_user_id'],
            mentionedByUserId: (int) $row['mentioned_by_user_id'],
            mentionedByName: (string) $row['author_name'],
            mentionedByUsername: (string) $row['author_username'],
            postBodyRaw: (string) $row['body_raw'],
            postPosition: isset($row['post_position']) ? (int) $row['post_position'] : 1,
            createdAt: (int) $row['created_at'],
            readAt: $row['read_at'] !== null ? (int) $row['read_at'] : null,
        );
    }
}
