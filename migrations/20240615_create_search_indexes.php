<?php

declare(strict_types=1);

use Fred\Infrastructure\Database\Migration\Migration;

return new class () implements Migration {
    public function getName(): string
    {
        return '20240615_create_search_indexes';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE VIRTUAL TABLE IF NOT EXISTS threads_fts USING fts5(
    title,
    community_id UNINDEXED,
    board_id UNINDEXED,
    thread_id UNINDEXED
);

CREATE VIRTUAL TABLE IF NOT EXISTS posts_fts USING fts5(
    body,
    community_id UNINDEXED,
    thread_id UNINDEXED,
    post_id UNINDEXED
);

CREATE TRIGGER IF NOT EXISTS trg_threads_ai AFTER INSERT ON threads BEGIN
    INSERT INTO threads_fts(rowid, title, community_id, board_id, thread_id)
    VALUES (new.id, new.title, new.community_id, new.board_id, new.id);
END;

CREATE TRIGGER IF NOT EXISTS trg_threads_ad AFTER DELETE ON threads BEGIN
    DELETE FROM threads_fts WHERE rowid = old.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_threads_au AFTER UPDATE ON threads BEGIN
    DELETE FROM threads_fts WHERE rowid = old.id;
    INSERT INTO threads_fts(rowid, title, community_id, board_id, thread_id)
    VALUES (new.id, new.title, new.community_id, new.board_id, new.id);
END;

CREATE TRIGGER IF NOT EXISTS trg_posts_ai AFTER INSERT ON posts BEGIN
    INSERT INTO posts_fts(rowid, body, community_id, thread_id, post_id)
    VALUES (new.id, new.body_raw, new.community_id, new.thread_id, new.id);
END;

CREATE TRIGGER IF NOT EXISTS trg_posts_ad AFTER DELETE ON posts BEGIN
    DELETE FROM posts_fts WHERE rowid = old.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_posts_au AFTER UPDATE OF body_raw ON posts BEGIN
    DELETE FROM posts_fts WHERE rowid = old.id;
    INSERT INTO posts_fts(rowid, body, community_id, thread_id, post_id)
    VALUES (new.id, new.body_raw, new.community_id, new.thread_id, new.id);
END;

INSERT INTO threads_fts(rowid, title, community_id, board_id, thread_id)
SELECT id, title, community_id, board_id, id FROM threads;

INSERT INTO posts_fts(rowid, body, community_id, thread_id, post_id)
SELECT id, body_raw, community_id, thread_id, id FROM posts;
SQL);
    }
};
