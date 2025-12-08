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
        return $this->listByBoardIdPaginated($boardId, 1000, 0);
    }

    /**
     * @return Thread[]
     */
    public function listByBoardIdPaginated(int $boardId, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.community_id, t.board_id, t.title, t.author_id, t.is_sticky, t.is_locked, t.is_announcement, t.created_at, t.updated_at,
                    u.display_name AS author_name
             FROM threads t
             JOIN users u ON u.id = t.author_id
             WHERE t.board_id = :board_id
             ORDER BY t.is_sticky DESC, t.created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $statement->bindValue(':board_id', $boardId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function countByBoardId(int $boardId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM threads WHERE board_id = :board_id');
        $statement->execute(['board_id' => $boardId]);

        return (int) $statement->fetchColumn();
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

    /**
     * Batch insert threads for seeding performance
     * @param array<array{communityId: int, boardId: int, title: string, authorId: int, isSticky: bool, isLocked: bool, isAnnouncement: bool, timestamp: int}> $threads
     * @return int First inserted thread ID
     */
    public function batchInsert(array $threads): int
    {
        if ($threads === []) {
            return 0;
        }

        // Chunk to avoid SQLite's 999 parameter limit (9 params per row, so 110 rows max)
        $chunks = array_chunk($threads, 110);
        $lastId = 0;
        
        foreach ($chunks as $chunk) {
            $placeholders = [];
            $values = [];
            
            foreach ($chunk as $thread) {
                $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
                $values[] = $thread['communityId'];
                $values[] = $thread['boardId'];
                $values[] = $thread['title'];
                $values[] = $thread['authorId'];
                $values[] = $thread['isSticky'] ? 1 : 0;
                $values[] = $thread['isLocked'] ? 1 : 0;
                $values[] = $thread['isAnnouncement'] ? 1 : 0;
                $values[] = $thread['timestamp'];
                $values[] = $thread['timestamp'];
            }

            $sql = 'INSERT INTO threads (community_id, board_id, title, author_id, is_sticky, is_locked, is_announcement, created_at, updated_at) VALUES '
                . implode(', ', $placeholders);
            
            $statement = $this->pdo->prepare($sql);
            $statement->execute($values);
            
            $lastId = (int) $this->pdo->lastInsertId();
        }

        return $lastId;
    }

    public function updateLock(int $threadId, bool $locked): void
    {
        $statement = $this->pdo->prepare('UPDATE threads SET is_locked = :locked, updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            'locked' => $locked ? 1 : 0,
            'updated_at' => time(),
            'id' => $threadId,
        ]);
    }

    public function updateSticky(int $threadId, bool $sticky): void
    {
        $statement = $this->pdo->prepare('UPDATE threads SET is_sticky = :sticky, updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            'sticky' => $sticky ? 1 : 0,
            'updated_at' => time(),
            'id' => $threadId,
        ]);
    }

    public function moveToBoard(int $threadId, int $boardId): void
    {
        $statement = $this->pdo->prepare('UPDATE threads SET board_id = :board_id, updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            'board_id' => $boardId,
            'updated_at' => time(),
            'id' => $threadId,
        ]);
    }

    public function updateAnnouncement(int $threadId, bool $announcement): void
    {
        $statement = $this->pdo->prepare('UPDATE threads SET is_announcement = :announcement, updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            'announcement' => $announcement ? 1 : 0,
            'updated_at' => time(),
            'id' => $threadId,
        ]);
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
