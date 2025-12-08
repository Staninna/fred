<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use Fred\Domain\Community\Board;
use PDO;

final class BoardRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return Board[]
     */
    public function listByCommunityId(int $communityId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, community_id, category_id, slug, name, description, position, is_locked, custom_css, created_at, updated_at
             FROM boards
             WHERE community_id = :community_id
             ORDER BY position ASC, id ASC',
        );

        $statement->execute(['community_id' => $communityId]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findById(int $id): ?Board
    {
        $statement = $this->pdo->prepare(
            'SELECT id, community_id, category_id, slug, name, description, position, is_locked, custom_css, created_at, updated_at
             FROM boards
             WHERE id = :id
             LIMIT 1',
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findBySlug(int $communityId, string $slug): ?Board
    {
        $statement = $this->pdo->prepare(
            'SELECT id, community_id, category_id, slug, name, description, position, is_locked, custom_css, created_at, updated_at
             FROM boards
             WHERE community_id = :community_id AND slug = :slug
             LIMIT 1',
        );
        $statement->execute([
            'community_id' => $communityId,
            'slug' => $slug,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function create(
        int $communityId,
        int $categoryId,
        string $slug,
        string $name,
        string $description,
        int $position,
        bool $isLocked,
        ?string $customCss,
        int $timestamp,
    ): Board {
        $statement = $this->pdo->prepare(
            'INSERT INTO boards (community_id, category_id, slug, name, description, position, is_locked, custom_css, created_at, updated_at)
             VALUES (:community_id, :category_id, :slug, :name, :description, :position, :is_locked, :custom_css, :created_at, :updated_at)',
        );

        $statement->execute([
            'community_id' => $communityId,
            'category_id' => $categoryId,
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'position' => $position,
            'is_locked' => $isLocked ? 1 : 0,
            'custom_css' => $customCss,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $board = $this->findById($id);

        if ($board === null) {
            throw new \RuntimeException('Failed to create board.');
        }

        return $board;
    }

    public function update(
        int $id,
        string $slug,
        string $name,
        string $description,
        int $position,
        bool $isLocked,
        ?string $customCss,
        int $timestamp,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE boards
             SET slug = :slug,
                 name = :name,
                 description = :description,
                 position = :position,
                 is_locked = :is_locked,
                 custom_css = :custom_css,
                 updated_at = :updated_at
             WHERE id = :id',
        );

        $statement->execute([
            'id' => $id,
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'position' => $position,
            'is_locked' => $isLocked ? 1 : 0,
            'custom_css' => $customCss,
            'updated_at' => $timestamp,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM boards WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private function hydrate(array $row): Board
    {
        return new Board(
            id: (int) $row['id'],
            communityId: (int) $row['community_id'],
            categoryId: (int) $row['category_id'],
            slug: (string) $row['slug'],
            name: (string) $row['name'],
            description: (string) $row['description'],
            position: (int) $row['position'],
            isLocked: (bool) $row['is_locked'],
            customCss: $row['custom_css'] !== null ? (string) $row['custom_css'] : null,
            createdAt: (int) $row['created_at'],
            updatedAt: (int) $row['updated_at'],
        );
    }
}
