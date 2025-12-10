<?php

declare(strict_types=1);

namespace Fred\Application\Admin;

use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Infrastructure\Database\CategoryRepository;
use RuntimeException;

final readonly class DeleteCategoryService
{
    public function __construct(private CategoryRepository $categories)
    {
    }

    public function delete(Community $community, Category $category): void
    {
        if ($category->communityId !== $community->id) {
            throw new RuntimeException('Category does not belong to this community.');
        }

        $this->categories->delete($category->id);
    }
}
