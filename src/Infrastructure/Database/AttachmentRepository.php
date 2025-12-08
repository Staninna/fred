<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use Fred\Domain\Forum\Attachment;
use PDO;

final class AttachmentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return Attachment[]
     */
    public function listByPostId(int $postId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, community_id, post_id, user_id, path, original_name, mime_type, size_bytes, created_at
             FROM attachments
             WHERE post_id = :post_id
             ORDER BY id ASC'
        );
        $statement->execute(['post_id' => $postId]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @param int[] $postIds
     * @return array<int, Attachment[]>
     */
    public function listByPostIds(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, \count($postIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT id, community_id, post_id, user_id, path, original_name, mime_type, size_bytes, created_at
             FROM attachments
             WHERE post_id IN ($placeholders)
             ORDER BY id ASC"
        );
        $statement->execute($postIds);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $grouped = [];

        foreach ($rows as $row) {
            $attachment = $this->hydrate($row);
            $grouped[$attachment->postId][] = $attachment;
        }

        return $grouped;
    }

    public function create(
        int $communityId,
        int $postId,
        int $userId,
        string $path,
        string $originalName,
        string $mimeType,
        int $sizeBytes,
        int $createdAt,
    ): Attachment {
        $statement = $this->pdo->prepare(
            'INSERT INTO attachments (community_id, post_id, user_id, path, original_name, mime_type, size_bytes, created_at)
             VALUES (:community_id, :post_id, :user_id, :path, :original_name, :mime_type, :size_bytes, :created_at)'
        );

        $statement->execute([
            'community_id' => $communityId,
            'post_id' => $postId,
            'user_id' => $userId,
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'created_at' => $createdAt,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $attachment = $this->findById($id);

        if ($attachment === null) {
            throw new \RuntimeException('Failed to create attachment.');
        }

        return $attachment;
    }

    public function findById(int $id): ?Attachment
    {
        $statement = $this->pdo->prepare(
            'SELECT id, community_id, post_id, user_id, path, original_name, mime_type, size_bytes, created_at
             FROM attachments
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    private function hydrate(array $row): Attachment
    {
        return new Attachment(
            id: (int) $row['id'],
            communityId: (int) $row['community_id'],
            postId: (int) $row['post_id'],
            userId: (int) $row['user_id'],
            path: (string) $row['path'],
            originalName: (string) $row['original_name'],
            mimeType: (string) $row['mime_type'],
            sizeBytes: (int) $row['size_bytes'],
            createdAt: (int) $row['created_at'],
        );
    }
}
