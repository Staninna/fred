<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database\Migration;

use PDO;
use RuntimeException;
use SplFileInfo;

use function array_map;
use function basename;
use function date;
use function glob;
use function sprintf;
use function usort;

final class MigrationRunner
{
    private const TABLE = 'migrations';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $directory,
    ) {
    }

    public function run(): void
    {
        $this->ensureMigrationsTable();

        $applied = $this->loadAppliedMigrations();
        $migrations = $this->discoverMigrations();

        foreach ($migrations as $file) {
            $migration = $this->requireMigration($file);
            $name = $migration->getName();

            if (isset($applied[$name])) {
                continue;
            }

            $this->pdo->beginTransaction();

            try {
                $migration->up($this->pdo);
                $this->markApplied($name);
                $this->pdo->commit();
            } catch (\Throwable $exception) {
                $this->pdo->rollBack();

                throw $exception;
            }
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    ran_at TEXT NOT NULL
);
SQL);
    }

    private function loadAppliedMigrations(): array
    {
        $statement = $this->pdo->query('SELECT name FROM ' . self::TABLE);

        $applied = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $applied[(string) $name] = true;
        }

        return $applied;
    }

    private function discoverMigrations(): array
    {
        $files = glob($this->directory . '/*.php');

        if ($files === false) {
            return [];
        }

        usort($files, static function (string $left, string $right) {
            return $left <=> $right;
        });

        return array_map(static fn (string $file) => new SplFileInfo($file), $files);
    }

    private function requireMigration(SplFileInfo $file): Migration
    {
        $migration = require $file->getPathname();

        if ($migration instanceof Migration) {
            return $migration;
        }

        $fileName = basename($file->getPathname());

        throw new RuntimeException(sprintf('Migration file %s must return a Migration instance.', $fileName));
    }

    private function markApplied(string $name): void
    {
        $statement = $this->pdo->prepare('INSERT INTO ' . self::TABLE . ' (name, ran_at) VALUES (:name, :ran_at)');
        $statement->execute([
            'name' => $name,
            'ran_at' => date(DATE_ATOM),
        ]);
    }
}
