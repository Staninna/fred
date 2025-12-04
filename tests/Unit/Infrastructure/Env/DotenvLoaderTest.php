<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Env;

use Fred\Infrastructure\Env\DotenvLoader;
use Tests\TestCase;

final class DotenvLoaderTest extends TestCase
{
    public function testLoadsVariablesAndSetsGlobals(): void
    {
        $tempDir = $this->createTempDir('fred-env-');
        $envPath = $tempDir . '/.env';

        file_put_contents($envPath, <<<ENV
FOO=bar
QUOTED="baz qux"
# comment
EMPTY=
TRIMMED= spaced value 
ENV);

        $variables = DotenvLoader::load($envPath);

        $this->assertSame('bar', $variables['FOO']);
        $this->assertSame('baz qux', $variables['QUOTED']);
        $this->assertSame('', $variables['EMPTY']);
        $this->assertSame('spaced value', $variables['TRIMMED']);

        $this->assertSame('bar', $_ENV['FOO']);
        $this->assertSame('bar', getenv('FOO'));

        $this->removeDirectory($tempDir);
    }
}
