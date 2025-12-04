<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Database;

use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\DatabaseResetter;
use Tests\TestCase;

final class DatabaseResetterTest extends TestCase
{
    public function testFreshDeletesSqliteFile(): void
    {
        $tempDir = $this->createTempDir('fred-db-');
        $dbPath = $tempDir . '/database.sqlite';
        file_put_contents($dbPath, 'test');

        $config = new AppConfig(
            environment: 'testing',
            baseUrl: 'http://example.test',
            databasePath: $dbPath,
            uploadsPath: $tempDir . '/uploads',
            logsPath: $tempDir . '/logs',
            basePath: $tempDir,
        );

        $resetter = new DatabaseResetter($config);
        $resetter->fresh();

        $this->assertFileDoesNotExist($dbPath);
        $this->removeDirectory($tempDir);
    }
}
