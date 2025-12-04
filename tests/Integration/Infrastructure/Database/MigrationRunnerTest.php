<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Database;

use Fred\Infrastructure\Database\Migration\MigrationRunner;
use Tests\TestCase;

final class MigrationRunnerTest extends TestCase
{
    public function testAppliesAllMigrations(): void
    {
        $pdo = $this->makeMigratedPdo();

        $count = (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
        $files = glob($this->basePath('migrations') . '/*.php') ?: [];

        $this->assertSame(count($files), $count);
    }

    public function testIsIdempotent(): void
    {
        $pdo = $this->makeMigratedPdo();

        $runner = new MigrationRunner($pdo, $this->basePath('migrations'));
        $runner->run();

        $count = (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
        $files = glob($this->basePath('migrations') . '/*.php') ?: [];

        $this->assertSame(count($files), $count);
    }
}
