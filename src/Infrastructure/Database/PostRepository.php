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

    /**
     * @return Post[]
     */
    public function listByThreadId(int $threadId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.community_id, p.thread_id, p.author_id, p.body_raw, p.body_parsed, p.signature_snapshot, p.created_at, p.updated_at,
                    u.display_name AS author_name
             FROM posts p
             JOIN users u ON u.id = p.author_id
             WHERE p.thread_id = :thread_id
             ORDER BY p.created_at ASC'
        );

        $statement->execute(['thread_id' => $threadId]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findById(int $id): ?Post
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.community_id, p.thread_id, p.author_id, p.body_raw, p.body_parsed, p.signature_snapshot, p.created_at, p.updated_at,
                    u.display_name AS author_name
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

    private function hydrate(array $row): Post
    {
        return new Post(
            id: (int) $row['id'],
            communityId: (int) $row['community_id'],
            threadId: (int) $row['thread_id'],
            authorId: (int) $row['author_id'],
            authorName: (string) $row['author_name'],
            bodyRaw: (string) $row['body_raw'],
            bodyParsed: $row['body_parsed'] !== null ? (string) $row['body_parsed'] : null,
            signatureSnapshot: $row['signature_snapshot'] !== null ? (string) $row['signature_snapshot'] : null,
            createdAt: (int) $row['created_at'],
            updatedAt: (int) $row['updated_at'],
        );
    }
}
