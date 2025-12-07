<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use PDO;

final readonly class CommunityModeratorRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function isModerator(int $communityId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM community_moderators WHERE community_id = :community_id AND user_id = :user_id LIMIT 1'
        );
        $statement->execute([
            'community_id' => $communityId,
            'user_id' => $userId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @return array<int, array{user_id:int, username:string, assigned_at:int}>
     */
    public function listByCommunity(int $communityId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT cm.user_id, u.username, cm.created_at AS assigned_at
             FROM community_moderators cm
             JOIN users u ON u.id = cm.user_id
             WHERE cm.community_id = :community_id
             ORDER BY u.username ASC'
        );
        $statement->execute(['community_id' => $communityId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function assign(int $communityId, int $userId, int $timestamp): void
    {
        $statement = $this->pdo->prepare(
            'INSERT OR IGNORE INTO community_moderators (community_id, user_id, created_at)
             VALUES (:community_id, :user_id, :created_at)'
        );
        $statement->execute([
            'community_id' => $communityId,
            'user_id' => $userId,
            'created_at' => $timestamp,
        ]);
    }

    public function remove(int $communityId, int $userId): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM community_moderators WHERE community_id = :community_id AND user_id = :user_id'
        );
        $statement->execute([
            'community_id' => $communityId,
            'user_id' => $userId,
        ]);
    }
}
