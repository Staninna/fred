<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use Fred\Application\Auth\CurrentUser;
use Fred\Application\Auth\PermissionService;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Thread;
use Fred\Infrastructure\Database\AttachmentRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use PDO;
use RuntimeException;
use Throwable;

use function time;
use function trim;

final readonly class CreateThreadService
{
    public function __construct(
        private PermissionService $permissions,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private BbcodeParser $parser,
        private ProfileRepository $profiles,
        private UploadService $uploads,
        private AttachmentRepository $attachments,
        private MentionService $mentions,
        private PDO $pdo,
    ) {
    }

    /**
     * @param array<string, mixed>|null $attachmentFile
     * @return array{thread: Thread, attachmentPath: ?string}
     */
    public function create(
        CurrentUser $currentUser,
        Community $community,
        Board $board,
        string $title,
        string $bodyText,
        ?array $attachmentFile = null,
    ): array {
        if (!$this->permissions->canCreateThread($currentUser)) {
            throw new RuntimeException('User cannot create threads');
        }

        if ($board->isLocked) {
            throw new RuntimeException('Board is locked');
        }

        $title = trim($title);
        $bodyText = trim($bodyText);

        if ($title === '') {
            throw new RuntimeException('Title is required');
        }

        if ($bodyText === '') {
            throw new RuntimeException('Body is required');
        }

        $attachmentPath = null;

        try {
            $this->pdo->beginTransaction();

            if ($attachmentFile !== null && ($attachmentFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $attachmentPath = $this->uploads->saveAttachment($attachmentFile);
                } catch (Throwable $exception) {
                    throw new RuntimeException('Attachment error: ' . $exception->getMessage(), 0, $exception);
                }
            }

            $timestamp = time();

            $thread = $this->threads->create(
                communityId: $community->id,
                boardId: $board->id,
                title: $title,
                authorId: $currentUser->id ?? 0,
                isSticky: false,
                isLocked: false,
                isAnnouncement: false,
                timestamp: $timestamp,
            );

            $profile = $currentUser->id !== null
                ? $this->profiles->findByUserAndCommunity($currentUser->id, $community->id)
                : null;

            $post = $this->posts->create(
                communityId: $community->id,
                threadId: $thread->id,
                authorId: $currentUser->id ?? 0,
                bodyRaw: $bodyText,
                bodyParsed: $this->parser->parse($bodyText, $community->slug),
                signatureSnapshot: $profile?->signatureParsed,
                timestamp: $timestamp,
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

            $this->mentions->notifyFromText(
                communityId: $community->id,
                postId: $post->id,
                authorId: $currentUser->id ?? 0,
                bodyRaw: $bodyText,
            );

            $this->pdo->commit();

            return [
                'thread' => $thread,
                'attachmentPath' => $attachmentPath,
            ];
        } catch (RuntimeException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($attachmentPath !== null) {
                $this->uploads->delete($attachmentPath);
            }

            throw $e;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($attachmentPath !== null) {
                $this->uploads->delete($attachmentPath);
            }

            throw new RuntimeException('Could not create thread. Please try again.', 0, $exception);
        }
    }
}
