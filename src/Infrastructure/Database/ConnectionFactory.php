<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use Fred\Infrastructure\Config\AppConfig;
use PDO;

final class ConnectionFactory
{
    public static function make(AppConfig $config): PDO
    {
        $dsn = 'sqlite:' . $config->databasePath;
        $pdo = new PDO($dsn, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}
