<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use Fred\Infrastructure\Database\AttachmentRepository;
use Fred\Infrastructure\Database\PostRepository;
use Throwable;

final readonly class AttachmentCleanupHelper
{
    public function __construct(
        private AttachmentRepository $attachments,
        private UploadService $uploads,
        private PostRepository $posts,
    ) {
    }

    /**
     * Delete all attachments for a post and remove files from disk.
     */
    public function deleteByPost(int $postId): void
    {
        $this->deleteAttachments($this->attachments->listByPostId($postId));
    }

    /**
     * Delete all attachments for a thread and remove files from disk.
     */
    public function deleteByThread(int $threadId): void
    {
        $posts = $this->posts->listByThreadId($threadId);
        $postIds = array_map(static fn ($p) => $p->id, $posts);
        $allAttachments = [];

        foreach ($postIds as $postId) {
            $allAttachments = array_merge($allAttachments, $this->attachments->listByPostId($postId));
        }

        $this->deleteAttachments($allAttachments);
    }

    /**
     */
    private function deleteAttachments(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            try {
                $this->uploads->delete($attachment->path);
            } catch (Throwable $e) {
                // Log but continue deleting other files
                // In production, log this to error handler
            }
        }
    }
}
