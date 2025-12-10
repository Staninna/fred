<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Database;

use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\UserRepository;
use Tests\TestCase;

final class RepositoryFlowTest extends TestCase
{
    public function testCreatesAndListsCoreEntities(): void
    {
        $pdo = $this->makeMigratedPdo();

        $roles = new RoleRepository($pdo);
        $users = new UserRepository($pdo);
        $communities = new CommunityRepository($pdo);
        $categories = new CategoryRepository($pdo);
        $boards = new BoardRepository($pdo);
        $threads = new ThreadRepository($pdo);
        $posts = new PostRepository($pdo);

        $roles->ensureDefaultRoles();
        $memberRole = $roles->findBySlug('member');
        $this->assertNotNull($memberRole);

        $user = $users->create('alice', 'Alice', 'hash', $memberRole->id, time());

        $community = $communities->create('main', 'Main', 'Desc', null, time());
        $this->assertSame('main', $community->slug);

        $category = $categories->create($community->id, 'General', 1, time());
        $this->assertSame($community->id, $category->communityId);

        $board = $boards->create(
            communityId: $community->id,
            categoryId: $category->id,
            slug: 'general',
            name: 'General Board',
            description: 'Hello',
            position: 1,
            isLocked: false,
            customCss: null,
            timestamp: time(),
        );
        $this->assertSame('general', $board->slug);

        $thread = $threads->create(
            communityId: $community->id,
            boardId: $board->id,
            title: 'Welcome',
            authorId: $user->id,
            isSticky: true,
            isLocked: false,
            isAnnouncement: false,
            timestamp: time(),
        );
        $this->assertSame('Welcome', $thread->title);

        $post = $posts->create(
            communityId: $community->id,
            threadId: $thread->id,
            authorId: $user->id,
            bodyRaw: 'First post',
            bodyParsed: '<p>First post</p>',
            signatureSnapshot: null,
            timestamp: time(),
        );
        $this->assertSame('First post', $post->bodyRaw);

        $threadList = $threads->listByBoardId($board->id);
        $this->assertCount(1, $threadList);
        $this->assertSame('Alice', $threadList[0]->authorName);

        $postList = $posts->listByThreadId($thread->id);
        $this->assertCount(1, $postList);
        $this->assertSame('Alice', $postList[0]->authorName);
    }
}
