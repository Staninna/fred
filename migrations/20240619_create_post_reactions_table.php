<?php

declare(strict_types=1);

use Fred\Infrastructure\Database\Migration\Migration;

return new class () implements Migration {
    public function getName(): string
    {
        return '20240619_create_post_reactions_table';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS post_reactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    community_id INTEGER NOT NULL,
    post_id INTEGER NOT NULL,
    emoticon TEXT NOT NULL,
    count INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    UNIQUE(post_id, emoticon),
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_post_reactions_post ON post_reactions(post_id);
CREATE INDEX IF NOT EXISTS idx_post_reactions_community ON post_reactions(community_id);
SQL);
    }
};
