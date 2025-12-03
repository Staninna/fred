<?php

declare(strict_types=1);

namespace Fred\Infrastructure\View;

use RuntimeException;

use function extract;
use function file_exists;
use function ob_get_clean;
use function ob_start;

final class ViewRenderer
{
    public function __construct(private readonly string $viewPath)
    {
    }

    public function render(string $template, array $data = []): string
    {
        $fullPath = rtrim($this->viewPath, '/') . '/' . ltrim($template, '/');

        if (!file_exists($fullPath)) {
            throw new RuntimeException('View not found: ' . $template);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $fullPath;

        return (string) ob_get_clean();
    }
}
