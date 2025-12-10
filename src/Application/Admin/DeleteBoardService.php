<?php

declare(strict_types=1);

namespace Fred\Application\Admin;

use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;
use Fred\Infrastructure\Database\BoardRepository;
use RuntimeException;

final readonly class DeleteBoardService
{
    public function __construct(private BoardRepository $boards)
    {
    }

    public function delete(Community $community, Board $board): void
    {
        if ($board->communityId !== $community->id) {
            throw new RuntimeException('Board does not belong to this community.');
        }

        $this->boards->delete($board->id);
    }
}
