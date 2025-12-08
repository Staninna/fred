<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Application\Content\BbcodeParser;
use Fred\Application\Content\MentionService;
use Fred\Application\Content\UploadService;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\AttachmentRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\View\ViewRenderer;

use function trim;

final readonly class PostController
{
    public function __construct(
        private AuthService $auth,
        private ViewRenderer $view,
        private AppConfig $config,
        private CommunityHelper $communityHelper,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private BbcodeParser $parser,
        private ProfileRepository $profiles,
        private PermissionService $permissions,
        private UploadService $uploads,
        private AttachmentRepository $attachments,
        private MentionService $mentions,
    ) {
    }

    public function store(Request $request): Response
    {
        $community = $request->attribute('community');
        $thread = $request->attribute('thread');
        $board = $request->attribute('board');

        if (!$community instanceof Community || $thread === null || !$board instanceof Board) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();

        if (!$this->permissions->canReply($currentUser)) {
            return new Response(
                status: 403,
                headers: ['Content-Type' => 'text/html; charset=utf-8'],
                body: 'Forbidden',
            );
        }

        if ($thread->isLocked || $board->isLocked) {
            return new Response(
                status: 403,
                headers: ['Content-Type' => 'text/html; charset=utf-8'],
                body: 'Thread is locked.',
            );
        }

        $bodyText = trim((string) ($request->body['body'] ?? ''));
        if ($bodyText === '') {
            $page = isset($request->body['page']) ? '?page=' . (int) $request->body['page'] : '';
            return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $page);
        }

        $attachmentFile = $request->files['attachment'] ?? null;
        $attachmentPath = null;
        if (\is_array($attachmentFile) && ($attachmentFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $attachmentPath = $this->uploads->saveAttachment($attachmentFile);
            } catch (\Throwable $exception) {
                return new Response(
                    status: 422,
                    headers: ['Content-Type' => 'text/plain; charset=utf-8'],
                    body: 'Attachment error: ' . $exception->getMessage(),
                );
            }
        }

        $profile = $currentUser->id !== null ? $this->profiles->findByUserAndCommunity($currentUser->id, $community->id) : null;
        $bodyParsed = $this->parser->parse($bodyText, $community->slug);
        $timestamp = time();
        $createdPost = $this->posts->create(
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
            postId: $createdPost->id,
            authorId: $currentUser->id ?? 0,
            bodyRaw: $bodyText,
        );

        if ($attachmentPath !== null) {
            $this->attachments->create(
                communityId: $community->id,
                postId: $createdPost->id,
                userId: $currentUser->id ?? 0,
                path: $attachmentPath,
                originalName: (string) ($attachmentFile['name'] ?? ''),
                mimeType: (string) ($attachmentFile['type'] ?? ''),
                sizeBytes: (int) ($attachmentFile['size'] ?? 0),
                createdAt: $timestamp,
            );
        }

        $page = isset($request->body['page']) ? '?page=' . (int) $request->body['page'] . '#post-' : '?#post-';
        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id . $page . $createdPost->id);
    }

    private function notFound(Request $request): Response
    {
        return Response::notFound(
            view: $this->view,
            request: $request,
        );
    }
}
