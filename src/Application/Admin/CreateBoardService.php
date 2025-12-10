<?php

declare(strict_types=1);

namespace Fred\Application\Admin;

use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use RuntimeException;

use function time;
use function trim;

final readonly class CreateBoardService
{
    public function __construct(
        private BoardRepository $boards,
        private BoardValidator $validator,
    ) {
    }

    public function create(
        Community $community,
        Category $category,
        string $name,
        string $slug,
        string $description,
        int $position,
        bool $isLocked,
        string $customCss,
    ): void {
        if ($category->communityId !== $community->id) {
            throw new RuntimeException('Category does not belong to this community.');
        }

        $slug = $this->validator->validateAndSlugify($community, $name, $slug, $customCss);

        $this->boards->create(
            communityId: $community->id,
            categoryId: $category->id,
            slug: $slug,
            name: trim($name),
            description: trim($description),
            position: $position,
            isLocked: $isLocked,
            customCss: trim($customCss) !== '' ? trim($customCss) : null,
            timestamp: time(),
        );
    }
}
