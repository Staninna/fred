<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use Fred\Application\Auth\CurrentUser;
use Fred\Application\Auth\PermissionService;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Thread;
use Fred\Infrastructure\Database\ThreadRepository;
use RuntimeException;

final readonly class ThreadStateService
{
    public function __construct(
        private PermissionService $permissions,
        private ThreadRepository $threads,
    ) {
    }

    public function setLock(
        CurrentUser $currentUser,
        Community $community,
        Thread $thread,
        bool $locked,
    ): void {
        if (!$this->permissions->canLockThread($currentUser, $community->id)) {
            throw new RuntimeException('User cannot lock threads');
        }

        $this->threads->updateLock($thread->id, $locked);
    }

    public function setSticky(
        CurrentUser $currentUser,
        Community $community,
        Thread $thread,
        bool $sticky,
    ): void {
        if (!$this->permissions->canStickyThread($currentUser, $community->id)) {
            throw new RuntimeException('User cannot sticky threads');
        }

        $this->threads->updateSticky($thread->id, $sticky);
    }

    public function setAnnouncement(
        CurrentUser $currentUser,
        Community $community,
        Thread $thread,
        bool $announcement,
    ): void {
        if (!$this->permissions->canModerate($currentUser, $community->id)) {
            throw new RuntimeException('User cannot announce threads');
        }

        $this->threads->updateAnnouncement($thread->id, $announcement);
    }
}
