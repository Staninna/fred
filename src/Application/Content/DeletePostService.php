<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use Fred\Application\Auth\CurrentUser;
use Fred\Application\Auth\PermissionService;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Post;
use Fred\Infrastructure\Database\PostRepository;
use RuntimeException;

final readonly class DeletePostService
{
    public function __construct(
        private PermissionService $permissions,
        private PostRepository $posts,
        private AttachmentCleanupHelper $attachmentCleanup,
    ) {
    }

    public function delete(
        CurrentUser $currentUser,
        Community $community,
        Post $post,
    ): void {
        if (!$this->permissions->canDeleteAnyPost($currentUser, $community->id)) {
            throw new RuntimeException('User cannot delete posts');
        }

        $this->attachmentCleanup->deleteByPost($post->id);
        $this->posts->delete($post->id);
    }
}
