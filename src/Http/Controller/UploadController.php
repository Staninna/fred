<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;

use function file_exists;
use function file_get_contents;
use function filesize;
use function pathinfo;
use function realpath;
use function str_starts_with;
use function strtolower;

use const PATHINFO_EXTENSION;

final readonly class UploadController
{
    public function __construct(private AppConfig $config)
    {
    }

    public function serve(Request $request): Response
    {
        $type = (string) ($request->params['type'] ?? '');
        $year = (string) ($request->params['year'] ?? '');
        $month = (string) ($request->params['month'] ?? '');
        $file = (string) ($request->params['file'] ?? '');

        if (!preg_match('/^[a-z]+$/', $type) || !preg_match('/^[0-9]{4}$/', $year) || !preg_match('/^[0-9]{2}$/', $month) || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
            return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
        }

        $base = rtrim($this->config->uploadsPath, '/');
        if (!str_starts_with($base, '/')) {
            $base = rtrim($this->config->basePath, '/') . '/' . ltrim($base, '/');
        }
        $baseReal = realpath($base) ?: $base;

        $candidate = $baseReal . '/' . $type . '/' . $year . '/' . $month . '/' . $file;
        $resolved = realpath($candidate);

        if ($resolved === false || !str_starts_with($resolved, $baseReal) || !file_exists($resolved)) {
            return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
        }

        $mimeType = $this->guessMimeType($resolved);
        $body = (string) file_get_contents($resolved);

        return new Response(
            status: 200,
            headers: [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) filesize($resolved),
            ],
            body: $body,
        );
    }

    private function guessMimeType(string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
