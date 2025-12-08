<?php

declare(strict_types=1);

namespace Fred\Support;

use function preg_replace;
use function strtolower;
use function trim;

final class Str
{
    public static function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';

        return trim((string) $slug, '-');
    }
}
