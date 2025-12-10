<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use Fred\Domain\Auth\Role;
use PDO;

final readonly class RoleRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function ensureDefaultRoles(): void
    {
        $statement = $this->pdo->prepare('INSERT OR IGNORE INTO roles (slug, name) VALUES (:slug, :name)');

        foreach ([
            ['slug' => 'guest', 'name' => 'Guest'],
            ['slug' => 'member', 'name' => 'Member'],
            ['slug' => 'moderator', 'name' => 'Moderator'],
            ['slug' => 'admin', 'name' => 'Administrator'],
        ] as $role) {
            $statement->execute($role);
        }
    }

    public function findBySlug(string $slug): ?Role
    {
        $statement = $this->pdo->prepare('SELECT id, slug, name FROM roles WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new Role(
            id: (int) $row['id'],
            slug: (string) $row['slug'],
            name: (string) $row['name'],
        );
    }

    /**
     * @return array<string>
     */
    public function getPermissionsForRole(string $roleSlug): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.slug
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN roles r ON r.id = rp.role_id
             WHERE r.slug = :slug'
        );
        $statement->execute(['slug' => $roleSlug]);

        /** @var string[] $permissions */
        $permissions = $statement->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return $permissions;
    }
}
