<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use Fred\Application\Auth\CurrentUser;
use Fred\Application\Auth\PermissionService;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Post;
use Fred\Domain\Forum\Thread;
use Fred\Infrastructure\Database\AttachmentRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use RuntimeException;
use Throwable;

use function time;
use function trim;

final readonly class CreateReplyService
{
    public function __construct(
        private PermissionService $permissions,
        private PostRepository $posts,
        private BbcodeParser $parser,
        private ProfileRepository $profiles,
        private UploadService $uploads,
        private AttachmentRepository $attachments,
        private MentionService $mentions,
    ) {
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int}|null $attachmentFile
     * @return array{post: Post, attachmentPath: ?string}
     */
    public function create(
        CurrentUser $currentUser,
        Community $community,
        Thread $thread,
        string $bodyText,
        ?array $attachmentFile = null,
    ): array {
        if (!$this->permissions->canReply($currentUser)) {
            throw new RuntimeException('User cannot reply');
        }

        if ($thread->isLocked) {
            throw new RuntimeException('Thread is locked');
        }

        $bodyText = trim($bodyText);

        if ($bodyText === '') {
            throw new RuntimeException('Body is required');
        }

        $attachmentPath = null;

        if ($attachmentFile !== null && $attachmentFile['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $attachmentPath = $this->uploads->saveAttachment($attachmentFile);
            } catch (Throwable $exception) {
                throw new RuntimeException('Attachment error: ' . $exception->getMessage(), 0, $exception);
            }
        }

        $profile = $currentUser->id !== null ? $this->profiles->findByUserAndCommunity($currentUser->id, $community->id) : null;
        $bodyParsed = $this->parser->parse($bodyText, $community->slug);
        $timestamp = time();

        $post = $this->posts->create(
            communityId: $community->id,
            threadId: $thread->id,
            authorId: $currentUser->id ?? 0,
            bodyRaw: $bodyText,
            bodyParsed: $bodyParsed,
            signatureSnapshot: $profile?->signatureParsed,
            timestamp: $timestamp,
        );

        $this->mentions->notifyFromText(
            communityId: $community->id,
            postId: $post->id,
            authorId: $currentUser->id ?? 0,
            bodyRaw: $bodyText,
        );

        if ($attachmentPath !== null) {
            $this->attachments->create(
                communityId: $community->id,
                postId: $post->id,
                userId: $currentUser->id ?? 0,
                path: $attachmentPath,
                originalName: (string) ($attachmentFile['name'] ?? ''),
                mimeType: (string) ($attachmentFile['type'] ?? ''),
                sizeBytes: (int) ($attachmentFile['size'] ?? 0),
                createdAt: $timestamp,
            );
        }

        return [
            'post' => $post,
            'attachmentPath' => $attachmentPath,
        ];
    }
}
