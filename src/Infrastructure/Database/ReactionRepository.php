<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use PDO;
use PDOException;

final class ReactionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param int[] $postIds
     * @return array<int, array<string, int>> Map of postId => [emoticon => count]
     */
    public function listByPostIds(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT post_id, emoticon, count FROM post_reactions WHERE post_id IN ($placeholders)"
        );
        $statement->execute($postIds);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $grouped = [];
        foreach ($rows as $row) {
            $postId = (int) $row['post_id'];
            $grouped[$postId][$row['emoticon']] = (int) $row['count'];
        }

        return $grouped;
    }

    public function increment(int $communityId, int $postId, string $emoticon, int $amount = 1): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO post_reactions (community_id, post_id, emoticon, count, created_at, updated_at)
             VALUES (:community_id, :post_id, :emoticon, :amount, :now, :now)
             ON CONFLICT(post_id, emoticon) DO UPDATE SET count = count + :amount, updated_at = :now'
        );

        $now = time();
        $statement->execute([
            'community_id' => $communityId,
            'post_id' => $postId,
            'emoticon' => $emoticon,
            'amount' => $amount,
            'now' => $now,
        ]);
    }

    public function decrement(int $postId, string $emoticon, int $amount = 1): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE post_reactions SET count = count - :amount, updated_at = :now WHERE post_id = :post_id AND emoticon = :emoticon'
        );

        $statement->execute([
            'post_id' => $postId,
            'emoticon' => $emoticon,
            'amount' => $amount,
            'now' => time(),
        ]);

        $this->pdo->prepare('DELETE FROM post_reactions WHERE post_id = :post_id AND emoticon = :emoticon AND count <= 0')
            ->execute([
                'post_id' => $postId,
                'emoticon' => $emoticon,
            ]);
    }

    public function findUserReaction(int $postId, int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT emoticon FROM post_reaction_users WHERE post_id = :post_id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['post_id' => $postId, 'user_id' => $userId]);

        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    /** @param int[] $postIds @return array<int, string> */
    public function listUserReactions(array $postIds, int $userId): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $params = $postIds;
        $params[] = $userId;

        $stmt = $this->pdo->prepare(
            "SELECT post_id, emoticon FROM post_reaction_users WHERE post_id IN ($placeholders) AND user_id = ?"
        );
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['post_id']] = (string) $row['emoticon'];
        }

        return $map;
    }

    /**
     * @param int[] $postIds
     * @return array<int, array<string, array{names: string[], extra: int}>> Map postId => emoticon => metadata
     */
    public function listUsersByPostIds(array $postIds, int $perReactionLimit = 12): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT pru.post_id, pru.emoticon, u.display_name
             FROM post_reaction_users pru
             JOIN users u ON u.id = pru.user_id
             WHERE pru.post_id IN ($placeholders)
             ORDER BY pru.post_id, pru.emoticon, pru.id"
        );
        $stmt->execute($postIds);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $grouped = [];
        foreach ($rows as $row) {
            $postId = (int) $row['post_id'];
            $emoticon = (string) $row['emoticon'];
            $name = (string) $row['display_name'];

            $entry = $grouped[$postId][$emoticon] ?? ['names' => [], 'extra' => 0];
            if (count($entry['names']) < $perReactionLimit) {
                $entry['names'][] = $name;
            } else {
                $entry['extra']++;
            }

            $grouped[$postId][$emoticon] = $entry;
        }

        return $grouped;
    }

    public function setUserReaction(int $communityId, int $postId, int $userId, string $emoticon): void
    {
        $this->pdo->beginTransaction();
        try {
            $existing = $this->findUserReaction($postId, $userId);

            if ($existing !== null && $existing === $emoticon) {
                $this->pdo->commit();

                return;
            }

            if ($existing !== null) {
                $this->decrement($postId, $existing, 1);
            }

            $now = time();
            $this->pdo->prepare(
                'INSERT INTO post_reaction_users (community_id, post_id, user_id, emoticon, created_at, updated_at)
                 VALUES (:community_id, :post_id, :user_id, :emoticon, :created_at, :updated_at)
                 ON CONFLICT(post_id, user_id) DO UPDATE SET emoticon = :emoticon, updated_at = :updated_at'
            )->execute([
                'community_id' => $communityId,
                'post_id' => $postId,
                'user_id' => $userId,
                'emoticon' => $emoticon,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->increment($communityId, $postId, $emoticon, 1);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function removeUserReaction(int $postId, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $existing = $this->findUserReaction($postId, $userId);
            if ($existing === null) {
                $this->pdo->commit();

                return;
            }

            $this->pdo->prepare('DELETE FROM post_reaction_users WHERE post_id = :post_id AND user_id = :user_id')
                ->execute([
                    'post_id' => $postId,
                    'user_id' => $userId,
                ]);

            $this->decrement($postId, $existing, 1);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
