<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Config;

use function is_dir;
use function mkdir;
use function rtrim;

final class ConfigLoader
{
    public static function fromArray(array $env, string $basePath): AppConfig
    {
        $basePath = rtrim($basePath, '/');

        $environment = $env['APP_ENV'] ?? 'local';
        $baseUrl = $env['APP_URL'] ?? 'http://localhost:8000';
        $databasePath = $env['DB_PATH'] ?? $basePath . '/storage/database.sqlite';
        $uploadsPath = $env['UPLOADS_PATH'] ?? $basePath . '/storage/uploads';
        $logsPath = $env['LOGS_PATH'] ?? $basePath . '/storage/logs';

        self::ensureDirectory(\dirname($databasePath));
        self::ensureDirectory($uploadsPath);
        self::ensureDirectory($logsPath);

        return new AppConfig(
            environment: $environment,
            baseUrl: $baseUrl,
            databasePath: $databasePath,
            uploadsPath: $uploadsPath,
            logsPath: $logsPath,
            basePath: $basePath,
        );
    }

    private static function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, recursive: true);
        }
    }
}
