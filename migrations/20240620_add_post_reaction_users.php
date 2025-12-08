<?php

declare(strict_types=1);

use Fred\Infrastructure\Database\Migration\Migration;

return new class () implements Migration {
    public function getName(): string
    {
        return '20240620_add_post_reaction_users';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS post_reaction_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    community_id INTEGER NOT NULL,
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    emoticon TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    UNIQUE(post_id, user_id),
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_pru_post ON post_reaction_users(post_id);
SQL);
    }
};