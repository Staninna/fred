<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use function basename;

use const FILEINFO_MIME_TYPE;

use function filesize;
use function finfo_file;
use function finfo_open;

use Fred\Infrastructure\Config\AppConfig;

use function is_dir;
use function is_file;
use function is_string;
use function ltrim;
use function mkdir;
use function move_uploaded_file;
use function pathinfo;

use const PATHINFO_EXTENSION;

use function rtrim;

use RuntimeException;

use function sprintf;
use function str_starts_with;
use function strtolower;
use function uniqid;
use function unlink;

use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

final class UploadService
{
    private const int AVATAR_MAX_BYTES = 512_000; // 500 KB
    private const int ATTACHMENT_MAX_BYTES = 2_000_000; // 2 MB

    /** @var array<string, string> */
    private array $allowedMimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly AppConfig $config)
    {
    }

    /**
     * @param array{name?:string, tmp_name?:string, size?:int, error?:int, type?:string} $file
     */
    public function saveAvatar(array $file): string
    {
        return $this->saveImage($file, self::AVATAR_MAX_BYTES, 'avatars');
    }

    /**
     * @param array{name?:string, tmp_name?:string, size?:int, error?:int, type?:string} $file
     */
    public function saveAttachment(array $file): string
    {
        return $this->saveImage($file, self::ATTACHMENT_MAX_BYTES, 'attachments');
    }

    /**
     * @param array{name?:string, tmp_name?:string, size?:int, error?:int, type?:string} $file
     */
    private function saveImage(array $file, int $maxSize, string $subdir): string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }

        $tmpPath = $file['tmp_name'] ?? '';

        if (!is_file($tmpPath)) {
            throw new RuntimeException('Temporary upload missing.');
        }

        $size = $file['size'] ?? filesize($tmpPath);

        if ($size === false || $size <= 0) {
            throw new RuntimeException('File is empty.');
        }

        if ($size > $maxSize) {
            throw new RuntimeException('File is too large.');
        }

        $mime = $this->detectMime($tmpPath, (string) ($file['type'] ?? ''));
        $extension = $this->allowedMimeMap[$mime] ?? null;

        if ($extension === null) {
            throw new RuntimeException('Unsupported file type.');
        }

        $original = $this->cleanFilename((string) ($file['name'] ?? 'upload.' . $extension));

        $relativeDir = $subdir . '/' . date('Y') . '/' . date('m');
        $absoluteDir = rtrim($this->config->uploadsPath, '/') . '/' . $relativeDir;

        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, recursive: true);
        }

        $targetName = uniqid('upload_', true) . '.' . $extension;
        $relativePath = $relativeDir . '/' . $targetName;
        $absolutePath = $absoluteDir . '/' . $targetName;

        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            throw new RuntimeException('Failed to store upload.');
        }

        if (!chmod($absolutePath, 0644) && is_file($absolutePath)) {
            // best effort
        }

        return $relativePath;
    }

    public function delete(string $relativePath): void
    {
        $base = rtrim($this->config->uploadsPath, '/');

        if (!str_starts_with($base, '/')) {
            $base = rtrim($this->config->basePath, '/') . '/' . ltrim($base, '/');
        }

        $absolute = rtrim($base, '/') . '/' . ltrim($relativePath, '/');

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function detectMime(string $path, string $fallbackType): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $path) : null;
        $fallback = strtolower($fallbackType);

        return is_string($mime) && $mime !== '' ? $mime : $fallback;
    }

    private function cleanFilename(string $name): string
    {
        $base = basename($name);
        $extension = strtolower((string) pathinfo($base, PATHINFO_EXTENSION));
        $stem = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $base) ?? 'upload';
        $stem = preg_replace('/-+/', '-', $stem) ?? $stem;
        $stem = trim($stem, '-_');

        $safeStem = $stem === '' ? 'upload' : $stem;
        $safeExt = $extension !== '' ? '.' . $extension : '';

        return sprintf('%s%s', $safeStem, $safeExt);
    }
}
