<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use Fred\Domain\Forum\Thread;
use PDO;

final class ThreadRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return Thread[]
     */
    public function listByBoardId(int $boardId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.community_id, t.board_id, t.title, t.author_id, t.is_sticky, t.is_locked, t.is_announcement, t.created_at, t.updated_at,
                    u.display_name AS author_name
             FROM threads t
             JOIN users u ON u.id = t.author_id
             WHERE t.board_id = :board_id
             ORDER BY t.is_sticky DESC, t.created_at DESC'
        );

        $statement->execute(['board_id' => $boardId]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findById(int $id): ?Thread
    {
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.community_id, t.board_id, t.title, t.author_id, t.is_sticky, t.is_locked, t.is_announcement, t.created_at, t.updated_at,
                    u.display_name AS author_name
             FROM threads t
             JOIN users u ON u.id = t.author_id
             WHERE t.id = :id
             LIMIT 1'
        );

        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function create(
        int $communityId,
        int $boardId,
        string $title,
        int $authorId,
        bool $isSticky,
        bool $isLocked,
        bool $isAnnouncement,
        int $timestamp,
    ): Thread {
        $statement = $this->pdo->prepare(
            'INSERT INTO threads (community_id, board_id, title, author_id, is_sticky, is_locked, is_announcement, created_at, updated_at)
             VALUES (:community_id, :board_id, :title, :author_id, :is_sticky, :is_locked, :is_announcement, :created_at, :updated_at)'
        );

        $statement->execute([
            'community_id' => $communityId,
            'board_id' => $boardId,
            'title' => $title,
            'author_id' => $authorId,
            'is_sticky' => $isSticky ? 1 : 0,
            'is_locked' => $isLocked ? 1 : 0,
            'is_announcement' => $isAnnouncement ? 1 : 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $thread = $this->findById($id);
        if ($thread === null) {
            throw new \RuntimeException('Failed to create thread.');
        }

        return $thread;
    }

    private function hydrate(array $row): Thread
    {
        return new Thread(
            id: (int) $row['id'],
            communityId: (int) $row['community_id'],
            boardId: (int) $row['board_id'],
            title: (string) $row['title'],
            authorId: (int) $row['author_id'],
            authorName: (string) $row['author_name'],
            isSticky: (bool) $row['is_sticky'],
            isLocked: (bool) $row['is_locked'],
            isAnnouncement: (bool) $row['is_announcement'],
            createdAt: (int) $row['created_at'],
            updatedAt: (int) $row['updated_at'],
        );
    }
}
