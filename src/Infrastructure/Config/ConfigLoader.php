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
        $uploadsPath = self::toAbsolutePath($env['UPLOADS_PATH'] ?? $basePath . '/public/uploads', $basePath);
        $logsPath = self::toAbsolutePath($env['LOGS_PATH'] ?? $basePath . '/storage/logs', $basePath);

        self::ensureDirectory(\dirname(self::toAbsolutePath($databasePath, $basePath)));
        self::ensureDirectory($uploadsPath);
        self::ensureDirectory($logsPath);

        return new AppConfig(
            environment: $environment,
            baseUrl: $baseUrl,
            databasePath: self::toAbsolutePath($databasePath, $basePath),
            uploadsPath: $uploadsPath,
            logsPath: $logsPath,
            basePath: $basePath,
        );
    }

    private static function toAbsolutePath(string $path, string $basePath): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }

    private static function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, recursive: true);
        }
    }
}
