<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use PDO;

final readonly class BanRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $userId, string $reason, ?int $expiresAt, int $timestamp): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO bans (user_id, reason, expires_at, created_at)
             VALUES (:user_id, :reason, :expires_at, :created_at)'
        );

        $statement->execute([
            'user_id' => $userId,
            'reason' => $reason,
            'expires_at' => $expiresAt,
            'created_at' => $timestamp,
        ]);
    }

    public function isBanned(int $userId, int $now): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM bans
             WHERE user_id = :user_id
               AND (expires_at IS NULL OR expires_at > :now)
             ORDER BY created_at DESC
             LIMIT 1'
        );

        $statement->execute([
            'user_id' => $userId,
            'now' => $now,
        ]);

        return $statement->fetchColumn() !== false;
    }
}
