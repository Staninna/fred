<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use Fred\Application\Auth\CurrentUser;
use Fred\Application\Auth\PermissionService;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Post;
use Fred\Infrastructure\Database\PostRepository;
use RuntimeException;

use function time;
use function trim;

final readonly class EditPostService
{
    public function __construct(
        private PermissionService $permissions,
        private PostRepository $posts,
        private BbcodeParser $parser,
        private MentionService $mentions,
    ) {
    }

    public function edit(
        CurrentUser $currentUser,
        Community $community,
        Post $post,
        string $bodyRaw,
    ): void {
        if (!$this->permissions->canEditAnyPost($currentUser, $community->id)) {
            throw new RuntimeException('User cannot edit posts');
        }

        $bodyRaw = trim($bodyRaw);

        if ($bodyRaw === '') {
            throw new RuntimeException('Body is required');
        }

        $this->posts->updateBody(
            id: $post->id,
            raw: $bodyRaw,
            parsed: $this->parser->parse($bodyRaw, $community->slug),
            timestamp: time(),
        );

        $this->mentions->notifyFromText(
            communityId: $community->id,
            postId: $post->id,
            authorId: $currentUser->id ?? $post->authorId,
            bodyRaw: $bodyRaw,
        );
    }
}
