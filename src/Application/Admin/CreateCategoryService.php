<?php

declare(strict_types=1);

namespace Fred\Application\Admin;

use Fred\Domain\Community\Community;
use Fred\Infrastructure\Database\CategoryRepository;
use RuntimeException;

use function time;
use function trim;

final readonly class CreateCategoryService
{
    public function __construct(private CategoryRepository $categories)
    {
    }

    public function create(
        Community $community,
        string $name,
        int $position = 0,
    ): void {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Category name is required.');
        }

        $this->categories->create($community->id, $name, $position, time());
    }
}
