<?php

declare(strict_types=1);

use Fred\Infrastructure\Database\Migration\Migration;

return new class implements Migration {
    public function getName(): string
    {
        return '20240602_create_roles_and_permissions';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INTEGER NOT NULL,
    permission_id INTEGER NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

INSERT OR IGNORE INTO roles (slug, name) VALUES
    ('guest', 'Guest'),
    ('member', 'Member');
SQL);
    }
};
