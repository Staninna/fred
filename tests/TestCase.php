<?php

declare(strict_types=1);

namespace Tests;

use Fred\Infrastructure\Database\Migration\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Support\FilesystemTrait;

abstract class TestCase extends BaseTestCase
{
    use FilesystemTrait;

    private ?string $sessionPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionPath = $this->createTempDir('fred-sessions-');
        session_save_path($this->sessionPath);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($this->sessionPath !== null) {
            $this->removeDirectory($this->sessionPath);
        }

        parent::tearDown();
    }

    protected function basePath(string $path = ''): string
    {
        $root = rtrim(dirname(__DIR__), '/');

        return $path === '' ? $root : $root . '/' . ltrim($path, '/');
    }

    protected function makeMigratedPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        $runner = new MigrationRunner($pdo, $this->basePath('migrations'));
        $runner->run();

        return $pdo;
    }
}
