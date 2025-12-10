<?php

declare(strict_types=1);

namespace Fred\Application\Admin;

use Fred\Domain\Community\Community;
use Fred\Infrastructure\Database\CategoryRepository;

use function time;

final readonly class ReorderCategoriesService
{
    public function __construct(private CategoryRepository $categories)
    {
    }

    /**
     * @param array<int, int> $positionMap Map of category ID to new position
     */
    public function reorder(Community $community, array $positionMap): void
    {
        $categories = $this->categories->listByCommunityId($community->id);
        $now = time();

        foreach ($categories as $category) {
            $newPosition = $positionMap[$category->id] ?? $category->position;
            $this->categories->update($category->id, $category->name, $newPosition, $now);
        }
    }
}
