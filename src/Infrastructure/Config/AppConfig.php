<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Config;

final readonly class AppConfig
{
    public function __construct(
        public string $environment,
        public string $baseUrl,
        public string $databasePath,
        public string $uploadsPath,
        public string $logsPath,
        public string $basePath,
    ) {
    }
}
