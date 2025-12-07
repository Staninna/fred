<?php

declare(strict_types=1);

use Fred\Infrastructure\Database\Migration\Migration;

return new class () implements Migration {
    public function getName(): string
    {
        return '20240613_seed_permissions';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
INSERT OR IGNORE INTO roles (slug, name) VALUES
    ('guest', 'Guest'),
    ('member', 'Member'),
    ('moderator', 'Moderator'),
    ('admin', 'Administrator');
SQL);

        $pdo->exec(<<<'SQL'
INSERT OR IGNORE INTO permissions (slug, name) VALUES
    ('thread.create', 'Create threads'),
    ('post.create', 'Create posts'),
    ('thread.lock', 'Lock or unlock threads'),
    ('thread.sticky', 'Sticky or unsticky threads'),
    ('thread.move', 'Move threads between boards'),
    ('post.delete_any', 'Delete any post'),
    ('post.edit_any', 'Edit any post'),
    ('user.ban', 'Ban users');
SQL);

        $assignments = [
            ['role' => 'member', 'permission' => 'thread.create'],
            ['role' => 'member', 'permission' => 'post.create'],
            ['role' => 'moderator', 'permission' => 'thread.create'],
            ['role' => 'moderator', 'permission' => 'post.create'],
            ['role' => 'moderator', 'permission' => 'thread.lock'],
            ['role' => 'moderator', 'permission' => 'thread.sticky'],
            ['role' => 'moderator', 'permission' => 'thread.move'],
            ['role' => 'moderator', 'permission' => 'post.delete_any'],
            ['role' => 'moderator', 'permission' => 'post.edit_any'],
            ['role' => 'moderator', 'permission' => 'user.ban'],
            ['role' => 'admin', 'permission' => 'thread.create'],
            ['role' => 'admin', 'permission' => 'post.create'],
            ['role' => 'admin', 'permission' => 'thread.lock'],
            ['role' => 'admin', 'permission' => 'thread.sticky'],
            ['role' => 'admin', 'permission' => 'thread.move'],
            ['role' => 'admin', 'permission' => 'post.delete_any'],
            ['role' => 'admin', 'permission' => 'post.edit_any'],
            ['role' => 'admin', 'permission' => 'user.ban'],
        ];

        $link = $pdo->prepare(<<<'SQL'
INSERT OR IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug = :permission
WHERE r.slug = :role
SQL);

        foreach ($assignments as $assignment) {
            $link->execute([
                'role' => $assignment['role'],
                'permission' => $assignment['permission'],
            ]);
        }
    }
};
