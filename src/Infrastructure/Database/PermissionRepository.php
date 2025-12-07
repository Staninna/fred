<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use PDO;

final class PermissionRepository
{
    private array $cache = [];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ensureDefaultPermissions(): void
    {
        $permissions = [
            ['slug' => 'thread.create', 'name' => 'Create threads'],
            ['slug' => 'post.create', 'name' => 'Create posts'],
            ['slug' => 'thread.lock', 'name' => 'Lock or unlock threads'],
            ['slug' => 'thread.sticky', 'name' => 'Sticky or unsticky threads'],
            ['slug' => 'thread.move', 'name' => 'Move threads between boards'],
            ['slug' => 'post.delete_any', 'name' => 'Delete any post'],
            ['slug' => 'post.edit_any', 'name' => 'Edit any post'],
            ['slug' => 'user.ban', 'name' => 'Ban users'],
        ];

        $statement = $this->pdo->prepare('INSERT OR IGNORE INTO permissions (slug, name) VALUES (:slug, :name)');
        foreach ($permissions as $permission) {
            $statement->execute($permission);
        }

        $rolePermissions = [
            'guest' => [],
            'member' => ['thread.create', 'post.create'],
            'moderator' => [
                'thread.create',
                'post.create',
                'thread.lock',
                'thread.sticky',
                'thread.move',
                'post.delete_any',
                'post.edit_any',
                'user.ban',
            ],
            'admin' => [
                'thread.create',
                'post.create',
                'thread.lock',
                'thread.sticky',
                'thread.move',
                'post.delete_any',
                'post.edit_any',
                'user.ban',
            ],
        ];

        $roleStatement = $this->pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $permissionStatement = $this->pdo->prepare('SELECT id FROM permissions WHERE slug = :slug LIMIT 1');
        $attachStatement = $this->pdo->prepare(
            'INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
        );

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            $roleStatement->execute(['slug' => $roleSlug]);
            $roleId = $roleStatement->fetchColumn();
            if ($roleId === false) {
                continue;
            }

            foreach ($permissionSlugs as $permissionSlug) {
                $permissionStatement->execute(['slug' => $permissionSlug]);
                $permissionId = $permissionStatement->fetchColumn();
                if ($permissionId === false) {
                    continue;
                }

                $attachStatement->execute([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $this->cache = [];
    }

    public function roleHasPermission(string $roleSlug, string $permissionSlug): bool
    {
        $permissions = $this->permissionsForRole($roleSlug);

        return \in_array($permissionSlug, $permissions, true);
    }

    /**
     * @return string[]
     */
    private function permissionsForRole(string $roleSlug): array
    {
        if (isset($this->cache[$roleSlug])) {
            return $this->cache[$roleSlug];
        }

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

        $this->cache[$roleSlug] = $permissions;

        return $permissions;
    }
}
