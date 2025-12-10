<?php

declare(strict_types=1);

namespace Fred\Application\Admin;

use Fred\Domain\Community\Community;
use Fred\Infrastructure\Database\BoardRepository;

use function time;

final readonly class ReorderBoardsService
{
    public function __construct(private BoardRepository $boards)
    {
    }

    /**
     * @param array<int, int> $positionMap Map of board ID to new position
     */
    public function reorder(Community $community, array $positionMap): void
    {
        $boards = $this->boards->listByCommunityId($community->id);
        $now = time();

        foreach ($boards as $board) {
            $newPosition = $positionMap[$board->id] ?? $board->position;
            $this->boards->update(
                id: $board->id,
                slug: $board->slug,
                name: $board->name,
                description: $board->description,
                position: $newPosition,
                isLocked: $board->isLocked,
                customCss: $board->customCss,
                timestamp: $now,
            );
        }
    }
}
