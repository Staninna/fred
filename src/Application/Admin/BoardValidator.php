<?php

declare(strict_types=1);

namespace Fred\Application\Admin;

use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;
use Fred\Infrastructure\Database\BoardRepository;
use RuntimeException;

use function preg_replace;
use function strlen;
use function strtolower;
use function trim;

final readonly class BoardValidator
{
    public function __construct(private BoardRepository $boards)
    {
    }

    public function validateAndSlugify(
        Community $community,
        string $name,
        string $slugInput,
        string $customCss,
        ?Board $existingBoard = null,
    ): string {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Board name is required.');
        }

        if (strlen($customCss) > 25000) {
            throw new RuntimeException('Board CSS is too long (max 25000 characters).');
        }

        $slug = $slugInput === '' ? $this->slugify($name) : $this->slugify($slugInput);

        if ($slug === '') {
            throw new RuntimeException('Board slug is required.');
        }

        $existing = $this->boards->findBySlug($community->id, $slug);

        if ($existing !== null && ($existingBoard === null || $existing->id !== $existingBoard->id)) {
            throw new RuntimeException('Board slug is already in use.');
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';

        return trim((string) $slug, '-');
    }
}
