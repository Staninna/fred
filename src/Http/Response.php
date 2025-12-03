<?php

declare(strict_types=1);

namespace Fred\Http;

use function header;
use function http_response_code;
use function is_array;

final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            $formattedValue = is_array($value) ? implode(', ', $value) : $value;
            header($name . ': ' . $formattedValue);
        }

        echo $this->body;
    }
}
