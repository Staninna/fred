<?php

declare(strict_types=1);

namespace Tests\Integration\Session;

use Fred\Infrastructure\Session\SqliteSessionHandler;
use Tests\TestCase;

final class SqliteSessionHandlerTest extends TestCase
{
    public function testReadWriteAndDestroy(): void
    {
        $pdo = $this->makeMigratedPdo();
        $handler = new SqliteSessionHandler($pdo);

        $this->assertTrue($handler->write('abc', 'payload'));
        $this->assertSame('payload', $handler->read('abc'));

        $this->assertTrue($handler->destroy('abc'));
        $this->assertSame('', $handler->read('abc'));
    }

    public function testGarbageCollectionRemovesOldSessions(): void
    {
        $pdo = $this->makeMigratedPdo();
        $handler = new SqliteSessionHandler($pdo);

        $pdo->prepare('INSERT INTO sessions (id, payload, last_activity) VALUES (:id, :payload, :last_activity)')
            ->execute(['id' => 'old', 'payload' => 'x', 'last_activity' => time() - 1000]);
        $handler->write('fresh', 'ok');

        $deleted = $handler->gc(10);
        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertSame('', $handler->read('old'));
        $this->assertSame('ok', $handler->read('fresh'));
    }
}
