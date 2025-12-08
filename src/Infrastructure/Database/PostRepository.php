<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use Fred\Domain\Forum\Post;
use PDO;

final class PostRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @return Post[]
     */
    public function listByThreadId(int $threadId): array
    {
        return $this->listByThreadIdPaginated($threadId, 1000, 0);
    }

    /**
     * @return Post[]
     */
    public function listByThreadIdPaginated(int $threadId, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.community_id, p.thread_id, p.author_id, p.body_raw, p.body_parsed, p.signature_snapshot, p.created_at, p.updated_at,
                    u.display_name AS author_name, u.username AS author_username
             FROM posts p
             JOIN users u ON u.id = p.author_id
             WHERE p.thread_id = :thread_id
             ORDER BY p.created_at ASC
             LIMIT :limit OFFSET :offset'
        );

        $statement->bindValue(':thread_id', $threadId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function countByThreadId(int $threadId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM posts WHERE thread_id = :thread_id');
        $statement->execute(['thread_id' => $threadId]);

        return (int) $statement->fetchColumn();
    }

    public function findById(int $id): ?Post
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.community_id, p.thread_id, p.author_id, p.body_raw, p.body_parsed, p.signature_snapshot, p.created_at, p.updated_at,
                    u.display_name AS author_name, u.username AS author_username
             FROM posts p
             JOIN users u ON u.id = p.author_id
             WHERE p.id = :id
             LIMIT 1'
        );

        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM posts WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function updateBody(int $id, string $raw, ?string $parsed, int $timestamp): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE posts
             SET body_raw = :body_raw,
                 body_parsed = :body_parsed,
                 updated_at = :updated_at
             WHERE id = :id'
        );

        $statement->execute([
            'body_raw' => $raw,
            'body_parsed' => $parsed,
            'updated_at' => $timestamp,
            'id' => $id,
        ]);
    }

    public function create(
        int $communityId,
        int $threadId,
        int $authorId,
        string $bodyRaw,
        ?string $bodyParsed,
        ?string $signatureSnapshot,
        int $timestamp,
    ): Post {
        $statement = $this->pdo->prepare(
            'INSERT INTO posts (community_id, thread_id, author_id, body_raw, body_parsed, signature_snapshot, created_at, updated_at)
             VALUES (:community_id, :thread_id, :author_id, :body_raw, :body_parsed, :signature_snapshot, :created_at, :updated_at)'
        );

        $statement->execute([
            'community_id' => $communityId,
            'thread_id' => $threadId,
            'author_id' => $authorId,
            'body_raw' => $bodyRaw,
            'body_parsed' => $bodyParsed,
            'signature_snapshot' => $signatureSnapshot,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $post = $this->findById($id);
        if ($post === null) {
            throw new \RuntimeException('Failed to create post.');
        }

        return $post;
    }

    /**
     * Batch insert posts for seeding performance
     * @param array<array{communityId: int, threadId: int, authorId: int, bodyRaw: string, bodyParsed: ?string, signatureSnapshot: ?string, timestamp: int}> $posts
     * @return int First inserted post ID
     */
    public function batchInsert(array $posts): int
    {
        if ($posts === []) {
            return 0;
        }

        // Chunk to avoid SQLite's 999 parameter limit (8 params per row, so 124 rows max)
        $chunks = array_chunk($posts, 124);
        $lastId = 0;
        
        foreach ($chunks as $chunk) {
            $placeholders = [];
            $values = [];
            
            foreach ($chunk as $post) {
                $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
                $values[] = $post['communityId'];
                $values[] = $post['threadId'];
                $values[] = $post['authorId'];
                $values[] = $post['bodyRaw'];
                $values[] = $post['bodyParsed'];
                $values[] = $post['signatureSnapshot'];
                $values[] = $post['timestamp'];
                $values[] = $post['timestamp'];
            }

            $sql = 'INSERT INTO posts (community_id, thread_id, author_id, body_raw, body_parsed, signature_snapshot, created_at, updated_at) VALUES '
                . implode(', ', $placeholders);
            
            $statement = $this->pdo->prepare($sql);
            $statement->execute($values);
            
            $lastId = (int) $this->pdo->lastInsertId();
        }

        return $lastId;
    }

    private function hydrate(array $row): Post
    {
        return new Post(
            id: (int) $row['id'],
            communityId: (int) $row['community_id'],
            threadId: (int) $row['thread_id'],
            authorId: (int) $row['author_id'],
            authorName: (string) $row['author_name'],
            authorUsername: (string) $row['author_username'],
            bodyRaw: (string) $row['body_raw'],
            bodyParsed: $row['body_parsed'] !== null ? (string) $row['body_parsed'] : null,
            signatureSnapshot: $row['signature_snapshot'] !== null ? (string) $row['signature_snapshot'] : null,
            createdAt: (int) $row['created_at'],
            updatedAt: (int) $row['updated_at'],
        );
    }
}
