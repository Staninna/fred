<?php

declare(strict_types=1);

use Fred\Infrastructure\Database\Migration\Migration;

return new class () implements Migration {
    public function getName(): string
    {
        return '20240605_update_communities_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE communities ADD COLUMN custom_css TEXT NULL');
        $pdo->exec('ALTER TABLE communities ADD COLUMN updated_at INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('UPDATE communities SET updated_at = created_at WHERE updated_at = 0');
    }
};
