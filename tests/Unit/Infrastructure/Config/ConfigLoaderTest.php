<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Config;

use function dirname;

use Fred\Infrastructure\Config\ConfigLoader;
use Tests\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testBuildsConfigAndEnsuresDirectories(): void
    {
        $basePath = $this->createTempDir('fred-config-');

        $env = [
            'APP_ENV' => 'testing',
            'APP_URL' => 'http://example.test',
            'DB_PATH' => $basePath . '/storage/database.sqlite',
            'UPLOADS_PATH' => $basePath . '/custom_uploads',
            'LOGS_PATH' => $basePath . '/custom_logs',
        ];

        $config = ConfigLoader::fromArray($env, $basePath);

        $this->assertSame('testing', $config->environment);
        $this->assertSame('http://example.test', $config->baseUrl);
        $this->assertSame($env['DB_PATH'], $config->databasePath);
        $this->assertSame($env['UPLOADS_PATH'], $config->uploadsPath);
        $this->assertSame($env['LOGS_PATH'], $config->logsPath);
        $this->assertSame(rtrim($basePath, '/'), $config->basePath);

        $this->assertDirectoryExists(dirname($config->databasePath));
        $this->assertDirectoryExists($config->uploadsPath);
        $this->assertDirectoryExists($config->logsPath);

        $this->removeDirectory($basePath);
    }
}
