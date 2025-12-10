<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Community\Board;
use Fred\Domain\Forum\Thread;
use Fred\Infrastructure\Database\ReactionRepository;
use RuntimeException;

use function strtolower;

final readonly class AddReactionService
{
    public function __construct(
        private ReactionRepository $reactions,
        private EmoticonSet $emoticons,
    ) {
    }

    public function add(
        CurrentUser $currentUser,
        int $communityId,
        Thread $thread,
        Board $board,
        int $postId,
        string $emoticon,
    ): void {
        if ($currentUser->isGuest()) {
            throw new RuntimeException('User must be logged in to add reactions.');
        }

        if ($thread->isLocked || $board->isLocked) {
            throw new RuntimeException('Cannot add reactions to locked threads or boards.');
        }

        $emoticon = strtolower($emoticon);

        if ($emoticon === '' || !$this->emoticons->isAllowed($emoticon)) {
            throw new RuntimeException('Invalid emoticon.');
        }

        $this->reactions->setUserReaction(
            communityId: $communityId,
            postId: $postId,
            userId: (int) $currentUser->id,
            emoticon: $emoticon,
        );
    }

    public function remove(
        CurrentUser $currentUser,
        int $postId,
    ): void {
        if ($currentUser->isGuest()) {
            throw new RuntimeException('User must be logged in to remove reactions.');
        }

        $this->reactions->removeUserReaction($postId, (int) $currentUser->id);
    }
}
