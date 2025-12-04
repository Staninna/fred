<?php

declare(strict_types=1);

use Fred\Infrastructure\Database\Migration\Migration;

return new class implements Migration {
    public function getName(): string
    {
        return '20240603_create_users_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    display_name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    role_id INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
);
SQL);
    }
};
