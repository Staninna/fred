<?php

declare(strict_types=1);

namespace Fred\Http;

use function parse_url;
use function trim;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array  $query,
        public array  $body,
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
            body: $_POST ?? [],
        );
    }
}
