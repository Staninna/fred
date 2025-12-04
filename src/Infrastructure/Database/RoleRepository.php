<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use Fred\Domain\Auth\Role;
use PDO;

final class RoleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ensureDefaultRoles(): void
    {
        $statement = $this->pdo->prepare('INSERT OR IGNORE INTO roles (slug, name) VALUES (:slug, :name)');

        foreach ([
            ['slug' => 'guest', 'name' => 'Guest'],
            ['slug' => 'member', 'name' => 'Member'],
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
}
