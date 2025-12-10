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

        $stmt = $pdo->query('SELECT COUNT(*) FROM migrations');

        if ($stmt === false) {
            $this->fail('Query failed');
        }
        $count = (int) $stmt->fetchColumn();
        $files = glob($this->basePath('migrations') . '/*.php') ?: [];

        $this->assertSame(count($files), $count);
    }

    public function testIsIdempotent(): void
    {
        $pdo = $this->makeMigratedPdo();

        $runner = new MigrationRunner($pdo, $this->basePath('migrations'));
        $runner->run();

        $stmt = $pdo->query('SELECT COUNT(*) FROM migrations');

        if ($stmt === false) {
            $this->fail('Query failed');
        }
        $count = (int) $stmt->fetchColumn();
        $files = glob($this->basePath('migrations') . '/*.php') ?: [];

        $this->assertSame(count($files), $count);
    }
}
