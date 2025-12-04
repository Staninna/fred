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
        public array  $params = [],
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
            params: [],
        );
    }

    public function withParams(array $params): self
    {
        return new self(
            method: $this->method,
            path: $this->path,
            query: $this->query,
            body: $this->body,
            params: $params,
        );
    }
}
