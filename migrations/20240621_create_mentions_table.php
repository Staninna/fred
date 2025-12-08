<?php

declare(strict_types=1);

use Fred\Infrastructure\Database\Migration\Migration;

return new class () implements Migration {
    public function getName(): string
    {
        return '20240621_create_mentions_table';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS mention_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    community_id INTEGER NOT NULL,
    post_id INTEGER NOT NULL,
    mentioned_user_id INTEGER NOT NULL,
    mentioned_by_user_id INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    read_at INTEGER NULL,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (mentioned_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mentioned_by_user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_mentions_unique ON mention_notifications (post_id, mentioned_user_id);
CREATE INDEX IF NOT EXISTS idx_mentions_user_created ON mention_notifications (mentioned_user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_mentions_user_unread ON mention_notifications (mentioned_user_id, read_at);
CREATE INDEX IF NOT EXISTS idx_mentions_community ON mention_notifications (community_id);
SQL);
    }
};
