<?php

declare(strict_types=1);

namespace Fred\Http;

use Fred\Application\Auth\AuthService;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewRenderer;

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

    public static function notFound(
        ?ViewRenderer $view = null,
        ?AppConfig $config = null,
        ?AuthService $auth = null,
        ?Request $request = null,
        string $body = '<h1>Not Found</h1>',
    ): self {
        if ($view !== null && $config !== null && $auth !== null) {
            $body = $view->render('errors/404.php', [
                'pageTitle' => 'Page not found',
                'path' => $request?->path ?? '',
                'activePath' => $request?->path ?? '',
                'environment' => $config->environment,
                'currentUser' => $auth->currentUser(),
            ]);
        }

        return new self(
            status: 404,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
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
