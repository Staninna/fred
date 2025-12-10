<?php

declare(strict_types=1);

namespace Fred\Infrastructure\View;

use function extract;
use function file_exists;
use function htmlspecialchars;
use function ltrim;
use function ob_get_clean;
use function ob_start;
use function rtrim;

use RuntimeException;

use function str_replace;

final class ViewRenderer
{
    /** @param array<string, mixed> $sharedData */
    public function __construct(
        private readonly string  $viewPath,
        private readonly ?string $defaultLayout = 'layout/default.php',
        private array $sharedData = [],
    ) {
    }

    public function share(string $key, mixed $value): void
    {
        $this->sharedData[$key] = $value;
    }

    /**
     * @param array<string, mixed>|ViewContext $data
     */
    public function render(string $template, array|ViewContext $data = [], ?string $layout = null): string
    {
        $dataArray = $data instanceof ViewContext ? $data->all() : $data;
        $basePath = rtrim($this->viewPath, '/');

        $renderPartial = function (string $partial, array|ViewContext $partialData = []) use ($basePath): string {
            $pData = $partialData instanceof ViewContext ? $partialData->all() : $partialData;

            return $this->renderFile($basePath . '/' . ltrim($partial, '/'), $pData, $basePath);
        };

        $content = $this->renderFile(
            $basePath . '/' . ltrim($template, '/'),
            array_merge($dataArray, ['renderPartial' => $renderPartial]),
            $basePath,
        );

        $chosenLayout = $layout === null ? $this->defaultLayout : $layout;

        if ($chosenLayout === null || $chosenLayout === '') {
            return $content;
        }

        return $this->renderFile(
            $basePath . '/' . ltrim($chosenLayout, '/'),
            array_merge($dataArray, [
                'content' => $content,
                'renderPartial' => $renderPartial,
            ]),
            $basePath,
        );
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $filePath, array $data, string $viewRoot): string
    {
        $data = array_merge($this->sharedData, $data);

        if (!file_exists($filePath)) {
            $relative = str_replace($viewRoot . '/', '', $filePath);
            throw new RuntimeException('View not found: ' . $relative);
        }

        $e = static fn (string $value, int $flags = ENT_QUOTES): string => htmlspecialchars($value, $flags, 'UTF-8');

        $renderPartial = function (string $partial, array $partialData = []) use ($viewRoot): string {
            return $this->renderFile($viewRoot . '/' . ltrim($partial, '/'), $partialData, $viewRoot);
        };

        extract($data, EXTR_SKIP);

        ob_start();
        include $filePath;

        return (string) ob_get_clean();
    }
}
