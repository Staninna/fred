<?php

declare(strict_types=1);

namespace Fred\Infrastructure\View;

use RuntimeException;

use function extract;
use function file_exists;
use function htmlspecialchars;
use function ltrim;
use function ob_get_clean;
use function ob_start;
use function rtrim;
use function str_replace;

final readonly class ViewRenderer
{
    public function __construct(
        private string  $viewPath,
        private ?string $defaultLayout = 'layout/default.php',
    ) {
    }

    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $basePath = rtrim($this->viewPath, '/');

        $renderPartial = function (string $partial, array $partialData = []) use ($basePath): string {
            return $this->renderFile($basePath . '/' . ltrim($partial, '/'), $partialData, $basePath);
        };

        $content = $this->renderFile(
            $basePath . '/' . ltrim($template, '/'),
            array_merge($data, ['renderPartial' => $renderPartial]),
            $basePath,
        );

        $chosenLayout = $layout === null ? $this->defaultLayout : $layout;
        if ($chosenLayout === null || $chosenLayout === '') {
            return $content;
        }

        return $this->renderFile(
            $basePath . '/' . ltrim($chosenLayout, '/'),
            array_merge($data, [
                'content' => $content,
                'renderPartial' => $renderPartial,
            ]),
            $basePath,
        );
    }

    private function renderFile(string $filePath, array $data, string $viewRoot): string
    {
        if (!file_exists($filePath)) {
            $relative = str_replace($viewRoot . '/', '', $filePath);
            throw new RuntimeException('View not found: ' . $relative);
        }

        $e = static fn (string $value, int $flags = ENT_QUOTES): string => htmlspecialchars($value, $flags, 'UTF-8');

        extract($data, EXTR_SKIP);

        ob_start();
        include $filePath;

        return (string) ob_get_clean();
    }
}
