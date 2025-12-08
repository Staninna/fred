<?php

declare(strict_types=1);

namespace Tests\Support;

trait FilesystemTrait
{
    protected function createTempDir(string $prefix): string
    {
        $path = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . $prefix . uniqid('', true);
        mkdir($path, 0777, true);

        return $path;
    }

    protected function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }
}
