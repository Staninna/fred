<?php

declare(strict_types=1);

namespace Fred\Application\Admin;

use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Infrastructure\Database\CategoryRepository;
use RuntimeException;

use function time;
use function trim;

final readonly class UpdateCategoryService
{
    public function __construct(private CategoryRepository $categories)
    {
    }

    public function update(
        Community $community,
        Category $category,
        string $name,
        int $position = 0,
    ): void {
        if ($category->communityId !== $community->id) {
            throw new RuntimeException('Category does not belong to this community.');
        }

        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Category name is required.');
        }

        $this->categories->update($category->id, $name, $position, time());
    }
}
