<?php

declare(strict_types=1);

namespace Fred\Http;

use function header;
use function http_response_code;

final readonly class Response
{
    public function __construct(
        public int    $status,
        public array  $headers,
        public string $body,
    ) {
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self(
            status: $status,
            headers: ['Location' => $location],
            body: '',
        );
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            $formattedValue = \is_array($value) ? implode(', ', $value) : $value;
            header($name . ': ' . $formattedValue);
        }

        echo $this->body;
    }
}
