<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use Fred\Infrastructure\Config\AppConfig;

use function basename;
use function closedir;
use function is_dir;
use function is_file;
use function ltrim;
use function opendir;
use function pathinfo;
use function preg_match;
use function readdir;
use function rtrim;
use function sort;
use function strtolower;

use const PATHINFO_FILENAME;

final class EmoticonSet
{
    /** @var array<string, array<int, array{code: string, filename: string, url: string}>> */
    private static array $globalCache = [];

    /** @var array<int, array{code: string, filename: string, url: string}> */
    private array $cache = [];

    /** @var array<string, string> */
    private array $cacheMap = [];

    public function __construct(private readonly AppConfig $config)
    {
    }

    /**
     * @return array<int, array{code: string, filename: string, url: string}>
     */
    public function all(): array
    {
        if ($this->cache !== []) {
            return $this->cache;
        }

        $cacheKey = rtrim($this->config->basePath, '/') . '|' . $this->config->environment;
        if (isset(self::$globalCache[$cacheKey])) {
            return $this->cache = self::$globalCache[$cacheKey];
        }

        $base = rtrim($this->config->basePath, '/') . '/public/emoticons';
        $files = array_merge(
            glob($base . '/*.png') ?: [],
            glob($base . '/*.gif') ?: [],
        );
        sort($files);

        $items = [];
        foreach ($files as $file) {
            $filename = basename($file);
            $code = strtolower((string) pathinfo($filename, PATHINFO_FILENAME));

            $items[] = [
                'code' => $code,
                'filename' => $filename,
                'url' => '/emoticons/' . ltrim($filename, '/'),
            ];
        }

        self::$globalCache[$cacheKey] = $items;

        return $this->cache = $items;
    }

    /**
     * @return array<string, string> code => url
     */
    public function urlsByCode(): array
    {
        if ($this->cacheMap !== []) {
            return $this->cacheMap;
        }

        foreach ($this->all() as $item) {
            $this->cacheMap[$item['code']] = $item['url'];
        }

        return $this->cacheMap;
    }

    public function isAllowed(string $code): bool
    {
        $code = strtolower($code);
        foreach ($this->all() as $item) {
            if ($item['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    public function urlFor(string $code): ?string
    {
        $code = strtolower($code);
        foreach ($this->all() as $item) {
            if ($item['code'] === $code) {
                return $item['url'];
            }
        }

        return null;
    }

    /** @return string[] */
    public function codes(): array
    {
        return array_map(static fn (array $item) => $item['code'], $this->all());
    }
}
