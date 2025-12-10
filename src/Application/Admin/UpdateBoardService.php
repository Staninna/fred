<?php

declare(strict_types=1);

namespace Fred\Application\Admin;

use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Infrastructure\Database\BoardRepository;
use RuntimeException;

use function time;
use function trim;

final readonly class UpdateBoardService
{
    public function __construct(
        private BoardRepository $boards,
        private BoardValidator $validator,
    ) {
    }

    public function update(
        Community $community,
        Board $board,
        Category $category,
        string $name,
        string $slug,
        string $description,
        int $position,
        bool $isLocked,
        string $customCss,
    ): void {
        if ($board->communityId !== $community->id) {
            throw new RuntimeException('Board does not belong to this community.');
        }

        if ($category->communityId !== $community->id) {
            throw new RuntimeException('Category does not belong to this community.');
        }

        $slug = $this->validator->validateAndSlugify($community, $name, $slug, $customCss, $board);

        $this->boards->update(
            id: $board->id,
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
