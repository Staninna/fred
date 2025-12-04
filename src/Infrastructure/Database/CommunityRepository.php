<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use Fred\Domain\Community\Community;
use PDO;

final readonly class CommunityRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return Community[]
     */
    public function all(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, slug, name, description, custom_css, created_at, updated_at
             FROM communities
             ORDER BY name ASC',
        );

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findBySlug(string $slug): ?Community
    {
        $statement = $this->pdo->prepare(
            'SELECT id, slug, name, description, custom_css, created_at, updated_at
             FROM communities
             WHERE slug = :slug
             LIMIT 1',
        );

        $statement->execute(['slug' => $slug]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function create(string $slug, string $name, string $description, ?string $customCss, int $timestamp): Community
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO communities (slug, name, description, custom_css, created_at, updated_at)
             VALUES (:slug, :name, :description, :custom_css, :created_at, :updated_at)',
        );

        $statement->execute([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'custom_css' => $customCss,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $community = $this->findBySlug($slug);
        if ($community === null) {
            throw new \RuntimeException('Failed to create community.');
        }

        return $community;
    }

    public function update(int $id, string $name, string $description, ?string $customCss, int $timestamp): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE communities
             SET name = :name,
                 description = :description,
                 custom_css = :custom_css,
                 updated_at = :updated_at
             WHERE id = :id',
        );

        $statement->execute([
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'custom_css' => $customCss,
            'updated_at' => $timestamp,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM communities WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private function hydrate(array $row): Community
    {
        return new Community(
            id: (int) $row['id'],
            slug: (string) $row['slug'],
            name: (string) $row['name'],
            description: (string) $row['description'],
            customCss: $row['custom_css'] !== null ? (string) $row['custom_css'] : null,
            createdAt: (int) $row['created_at'],
            updatedAt: (int) $row['updated_at'],
        );
    }
}
