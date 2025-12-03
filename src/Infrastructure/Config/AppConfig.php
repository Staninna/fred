<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Config;

final class AppConfig
{
    public function __construct(
        public readonly string $environment,
        public readonly string $baseUrl,
        public readonly string $databasePath,
        public readonly string $uploadsPath,
        public readonly string $logsPath,
        public readonly string $basePath,
    ) {
    }
}
