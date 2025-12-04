<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use Fred\Infrastructure\Config\AppConfig;

final readonly class DatabaseResetter
{
    public function __construct(private AppConfig $config)
    {
    }

    public function fresh(): void
    {
        $path = $this->config->databasePath;

        if ($path === ':memory:') {
            return;
        }

        if (is_file($path)) {
            unlink($path);
        }
    }
}
