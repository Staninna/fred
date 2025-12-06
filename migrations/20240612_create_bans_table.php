<?php

declare(strict_types=1);

use Fred\Infrastructure\Database\Migration\Migration;

return new class () implements Migration {
    public function getName(): string
    {
        return '20240612_create_bans_table';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS bans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    reason TEXT NOT NULL,
    expires_at INTEGER NULL,
    created_at INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_bans_user_id ON bans(user_id);
SQL);
    }
};
