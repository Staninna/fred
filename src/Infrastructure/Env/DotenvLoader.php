<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Env;

use function strlen;

final class DotenvLoader
{
    /**
     * Load key/value pairs from a .env style file.
     * Comments and empty lines are ignored; quoted values are unwrapped.
     * @return array<string, string>
     */
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        $variables = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = self::stripQuotes(trim($value));

            $variables[$key] = $value;
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }

        return $variables;
    }

    private static function stripQuotes(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (strlen($value) >= 2) {
            $hasDoubleQuotes = str_starts_with($value, '"') && str_ends_with($value, '"');
            $hasSingleQuotes = str_starts_with($value, "'") && str_ends_with($value, "'");

            if ($hasDoubleQuotes || $hasSingleQuotes) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}
