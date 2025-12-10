<?php

declare(strict_types=1);

namespace Fred\Application\Support;

use function strlen;
use function trim;

final readonly class TextInputSanitizer
{
    /**
     * Trim and validate non-empty text input.
     *
     * @throws \RuntimeException
     */
    public static function validateNonEmpty(string $input, string $fieldName, int $maxLength = 0): string
    {
        $value = trim($input);

        if ($value === '') {
            throw new \RuntimeException($fieldName . ' is required.');
        }

        if ($maxLength > 0 && strlen($value) > $maxLength) {
            throw new \RuntimeException($fieldName . ' exceeds maximum length of ' . $maxLength . ' characters.');
        }

        return $value;
    }

    /**
     * Trim text input (allows empty).
     */
    public static function trim(string $input): string
    {
        return trim($input);
    }

    /**
     * Validate length constraints.
     *
     * @throws \RuntimeException
     */
    public static function validateLength(string $input, int $minLength = 0, int $maxLength = 0, string $fieldName = 'Input'): string
    {
        $value = trim($input);
        $length = strlen($value);

        if ($minLength > 0 && $length < $minLength) {
            throw new \RuntimeException($fieldName . ' must be at least ' . $minLength . ' characters.');
        }

        if ($maxLength > 0 && $length > $maxLength) {
            throw new \RuntimeException($fieldName . ' must not exceed ' . $maxLength . ' characters.');
        }

        return $value;
    }
}
