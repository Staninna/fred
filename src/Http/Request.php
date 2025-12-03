<?php

declare(strict_types=1);

namespace Fred\Http;

use function parse_url;
use function trim;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $path = (string) parse_url($uri, PHP_URL_PATH);
        $path = $path === '' ? '/' : $path;

        return new self(
            method: trim($method),
            path: $path,
            query: $_GET ?? [],
        );
    }
}
