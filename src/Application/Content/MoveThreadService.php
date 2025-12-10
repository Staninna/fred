<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use Fred\Application\Auth\CurrentUser;
use Fred\Application\Auth\PermissionService;
use Fred\Domain\Community\Community;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use RuntimeException;

final readonly class MoveThreadService
{
    public function __construct(
        private PermissionService $permissions,
        private ThreadRepository $threads,
        private BoardRepository $boards,
    ) {
    }

    public function move(
        CurrentUser $currentUser,
        Community $community,
        int $threadId,
        int $targetBoardId,
    ): void {
        if (!$this->permissions->canMoveThread($currentUser, $community->id)) {
            throw new RuntimeException('User cannot move threads');
        }

        $targetBoard = $this->boards->findById($targetBoardId);

        if ($targetBoard === null || $targetBoard->communityId !== $community->id) {
            throw new RuntimeException('Target board not found or does not belong to this community.');
        }

        $this->threads->moveToBoard($threadId, $targetBoardId);
    }
}
