<?php

declare(strict_types=1);

namespace Fred\Http;

use Fred\Application\Auth\AuthService;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;

use function header;
use function http_response_code;
use function is_array;

use Throwable;

final readonly class Response
{
    /** @param array<string, string|string[]> $headers */
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
            headers: [
                'Location' => $location,
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ],
            body: '',
        );
    }

    /** @param array<int, array{title: string, items: array<int, array{label: string, href: string}>}>|null $navSections */
    public static function notFound(
        ?ViewRenderer $view = null,
        ?AppConfig $config = null,
        ?AuthService $auth = null,
        ?Request $request = null,
        ?array $navSections = null,
        string $body = '<h1>Not Found</h1>',
        ?string $context = null,
    ): self {
        if ($view !== null) {
            $debugContext = ($config?->environment === 'local' && $context !== null) ? $context : null;

            $body = $view->render('errors/404.php', [
                'pageTitle' => 'Page not found',
                'path' => $request !== null ? $request->path : '',
                'debugContext' => $debugContext,
            ]);
        }

        return new self(
            status: 404,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    /**
     * Create a 403 Forbidden response.
     *
     * @param ?ViewRenderer $view If provided, renders error template; otherwise returns plain text.
     */
    public static function forbidden(
        ?ViewRenderer $view = null,
        string $body = '<h1>Forbidden</h1>',
    ): self {
        if ($view !== null) {
            try {
                $body = $view->render('errors/403.php', [
                    'pageTitle' => 'Access denied',
                ]);
            } catch (Throwable) {
                // Fall through to default body on render failure
            }
        }

        return new self(
            status: 403,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    /**
     * Create a 419 CSRF Failure response.
     *
     * @param ?ViewRenderer $view If provided, renders error template; otherwise returns plain text.
     */
    public static function csrfFailure(
        ?ViewRenderer $view = null,
        string $body = '<h1>CSRF token mismatch</h1>',
    ): self {
        if ($view !== null) {
            $body = $view->render('errors/419.php', [
                'pageTitle' => 'CSRF token mismatch',
            ]);
        }

        return new self(
            status: 419,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    /**
     * Create an HTML response with rendered view.
     *
     * @param array<string, mixed>|ViewContext $data
     */
    public static function view(
        ViewRenderer $view,
        string $template,
        array|ViewContext $data = [],
        ?string $layout = null,
        int $status = 200,
    ): self {
        $body = $view->render($template, $data, $layout);

        return new self(
            status: $status,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    public function send(): void
    {
        if (headers_sent()) {
            echo $this->body;

            return;
        }

        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            $formattedValue = is_array($value) ? implode(', ', $value) : $value;
            header($name . ': ' . $formattedValue, replace: true);
        }

        echo $this->body;
    }
}
