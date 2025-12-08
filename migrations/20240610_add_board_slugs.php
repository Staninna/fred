<?php

declare(strict_types=1);

use Fred\Infrastructure\Database\Migration\Migration;

return new class () implements Migration {
    public function getName(): string
    {
        return '20240610_add_board_slugs';
    }

    public function up(PDO $pdo): void
    {
        if ($this->columnExists($pdo, 'boards', 'slug')) {
            return;
        }

        $pdo->exec('ALTER TABLE boards ADD COLUMN slug TEXT');

        $this->backfillSlugs($pdo);

        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_boards_community_slug ON boards (community_id, slug)');
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $statement = $pdo->prepare('PRAGMA table_info(' . $table . ')');
        $rows = $statement->execute() ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($rows as $row) {
            if (($row['name'] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }

    private function backfillSlugs(PDO $pdo): void
    {
        $statement = $pdo->query('SELECT id, community_id, name FROM boards ORDER BY id ASC');
        $rows = $statement?->fetchAll(PDO::FETCH_ASSOC) ?? [];

        $used = [];
        $update = $pdo->prepare('UPDATE boards SET slug = :slug WHERE id = :id');

        foreach ($rows as $row) {
            $communityId = (int) $row['community_id'];
            $base = $this->slugify((string) $row['name']);

            if ($base === '') {
                $base = 'board-' . (int) $row['id'];
            }

            $slug = $base;
            $suffix = 1;

            while (isset($used[$communityId][$slug])) {
                $slug = $base . '-' . $suffix;
                $suffix++;
            }

            $used[$communityId][$slug] = true;

            $update->execute([
                'slug' => $slug,
                'id' => (int) $row['id'],
            ]);
        }
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
};
