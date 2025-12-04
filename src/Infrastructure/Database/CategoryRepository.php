<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use Fred\Domain\Community\Category;
use PDO;

final readonly class CategoryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return Category[]
     */
    public function listByCommunityId(int $communityId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, community_id, name, position, created_at, updated_at
             FROM categories
             WHERE community_id = :community_id
             ORDER BY position ASC, id ASC',
        );

        $statement->execute(['community_id' => $communityId]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findById(int $id): ?Category
    {
        $statement = $this->pdo->prepare(
            'SELECT id, community_id, name, position, created_at, updated_at
             FROM categories
             WHERE id = :id
             LIMIT 1',
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function create(int $communityId, string $name, int $position, int $timestamp): Category
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO categories (community_id, name, position, created_at, updated_at)
             VALUES (:community_id, :name, :position, :created_at, :updated_at)',
        );

        $statement->execute([
            'community_id' => $communityId,
            'name' => $name,
            'position' => $position,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $category = $this->findById($id);
        if ($category === null) {
            throw new \RuntimeException('Failed to create category.');
        }

        return $category;
    }

    public function update(int $id, string $name, int $position, int $timestamp): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE categories
             SET name = :name,
                 position = :position,
                 updated_at = :updated_at
             WHERE id = :id',
        );

        $statement->execute([
            'id' => $id,
            'name' => $name,
            'position' => $position,
            'updated_at' => $timestamp,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private function hydrate(array $row): Category
    {
        return new Category(
            id: (int) $row['id'],
            communityId: (int) $row['community_id'],
            name: (string) $row['name'],
            position: (int) $row['position'],
            createdAt: (int) $row['created_at'],
            updatedAt: (int) $row['updated_at'],
        );
    }
}
