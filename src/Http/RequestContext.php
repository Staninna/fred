<?php

declare(strict_types=1);

namespace Fred\Http;

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Post;
use Fred\Domain\Forum\Thread;

final readonly class RequestContext
{
    public function __construct(
        public ?CurrentUser $currentUser = null,
        public ?Community $community = null,
        public ?Category $category = null,
        public ?Board $board = null,
        public ?Thread $thread = null,
        public ?Post $post = null,
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    public function withCurrentUser(?CurrentUser $user): self
    {
        return new self(
            currentUser: $user,
            community: $this->community,
            category: $this->category,
            board: $this->board,
            thread: $this->thread,
            post: $this->post,
        );
    }

    public function withCommunity(?Community $community): self
    {
        return new self(
            currentUser: $this->currentUser,
            community: $community,
            category: $this->category,
            board: $this->board,
            thread: $this->thread,
            post: $this->post,
        );
    }

    public function withCategory(?Category $category): self
    {
        return new self(
            currentUser: $this->currentUser,
            community: $this->community,
            category: $category,
            board: $this->board,
            thread: $this->thread,
            post: $this->post,
        );
    }

    public function withBoard(?Board $board): self
    {
        return new self(
            currentUser: $this->currentUser,
            community: $this->community,
            category: $this->category,
            board: $board,
            thread: $this->thread,
            post: $this->post,
        );
    }

    public function withThread(?Thread $thread): self
    {
        return new self(
            currentUser: $this->currentUser,
            community: $this->community,
            category: $this->category,
            board: $this->board,
            thread: $thread,
            post: $this->post,
        );
    }

    public function withPost(?Post $post): self
    {
        return new self(
            currentUser: $this->currentUser,
            community: $this->community,
            category: $this->category,
            board: $this->board,
            thread: $this->thread,
            post: $post,
        );
    }
}
