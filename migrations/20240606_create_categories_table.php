<?php

declare(strict_types=1);

use Fred\Infrastructure\Database\Migration\Migration;

return new class () implements Migration {
    public function getName(): string
    {
        return '20240606_create_categories_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    community_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    position INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_categories_community_position ON categories (community_id, position);
SQL);
    }
};
