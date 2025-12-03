<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Session;

use PDO;
use SessionHandlerInterface;

use function time;

final class SqliteSessionHandler implements SessionHandlerInterface
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->ensureTable();
    }

    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        $statement = $this->pdo->prepare('SELECT payload FROM sessions WHERE id = :id');
        $statement->execute(['id' => $id]);

        $result = $statement->fetchColumn();

        return $result === false ? '' : (string) $result;
    }

    public function write($id, $data): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO sessions (id, payload, last_activity) VALUES (:id, :payload, :last_activity)
            ON CONFLICT(id) DO UPDATE SET payload = excluded.payload, last_activity = excluded.last_activity'
        );

        return $statement->execute([
            'id' => $id,
            'payload' => $data,
            'last_activity' => time(),
        ]);
    }

    public function destroy($id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM sessions WHERE id = :id');

        return $statement->execute(['id' => $id]);
    }

    public function gc($max_lifetime): int|false
    {
        $statement = $this->pdo->prepare('DELETE FROM sessions WHERE last_activity < :threshold');
        $statement->execute([
            'threshold' => time() - $max_lifetime,
        ]);

        return $statement->rowCount();
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS sessions (
    id TEXT PRIMARY KEY,
    payload TEXT NOT NULL,
    last_activity INTEGER NOT NULL
);
SQL);
    }
}
