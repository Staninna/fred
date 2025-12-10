<?php

declare(strict_types=1);

namespace Fred\Http;

use function function_exists;
use function is_array;
use function parse_url;
use function trim;

use Fred\Http\RequestContext;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array  $query,
        public array  $body,
        public array  $files = [],
        public array  $params = [],
        public array  $headers = [],
        public array  $session = [],
        public array  $attributes = [],
        public RequestContext $context = new RequestContext(),
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $path = (string) parse_url($uri, PHP_URL_PATH);
        $path = $path === '' ? '/' : $path;

        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];

        if ($headers === []) {
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                    $headers[$headerName] = $value;
                }
            }
        }

        $normalizedHeaders = [];

        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower((string) $key)] = $value;
        }

        $session = $_SESSION ?? [];

        if (!is_array($session)) {
            $session = [];
        }

        return new self(
            method: trim($method),
            path: $path,
            query: $_GET ?? [],
            body: $_POST ?? [],
            files: $_FILES ?? [],
            params: [],
            headers: $normalizedHeaders,
            session: $session,
            attributes: [],
            context: RequestContext::empty(),
        );
    }

    public function withParams(array $params): self
    {
        return new self(
            method: $this->method,
            path: $this->path,
            query: $this->query,
            body: $this->body,
            files: $this->files,
            params: $params,
            headers: $this->headers,
            session: $this->session,
            attributes: $this->attributes,
            context: $this->context,
        );
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return new self(
            method: $this->method,
            path: $this->path,
            query: $this->query,
            body: $this->body,
            files: $this->files,
            params: $this->params,
            headers: $this->headers,
            session: $this->session,
            attributes: $attributes,
            context: $this->context,
        );
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withContext(RequestContext $context): self
    {
        return new self(
            method: $this->method,
            path: $this->path,
            query: $this->query,
            body: $this->body,
            files: $this->files,
            params: $this->params,
            headers: $this->headers,
            session: $this->session,
            attributes: $this->attributes,
            context: $context,
        );
    }

    public function context(): RequestContext
    {
        return $this->context;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $normalized = strtolower($name);

        return $this->headers[$normalized] ?? $default;
    }
}
