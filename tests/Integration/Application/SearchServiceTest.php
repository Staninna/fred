<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use Fred\Application\Search\SearchService;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Tests\TestCase;

final class SearchServiceTest extends TestCase
{
    private \PDO $pdo;
    private CommunityRepository $communities;
    private CategoryRepository $categories;
    private BoardRepository $boards;
    private ThreadRepository $threads;
    private PostRepository $posts;
    private UserRepository $users;
    private RoleRepository $roles;
    private SearchService $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->makeMigratedPdo();
        $this->communities = new CommunityRepository($this->pdo);
        $this->categories = new CategoryRepository($this->pdo);
        $this->boards = new BoardRepository($this->pdo);
        $this->threads = new ThreadRepository($this->pdo);
        $this->posts = new PostRepository($this->pdo);
        $this->users = new UserRepository($this->pdo);
        $this->roles = new RoleRepository($this->pdo);
        $this->roles->ensureDefaultRoles();
        $this->search = new SearchService($this->pdo);
    }

    private function seed(): array
    {
        $timestamp = time();
        $community = $this->communities->create('demo', 'Demo', 'Demo community', null, $timestamp);
        $category = $this->categories->create($community->id, 'General', 1, $timestamp);
        $board = $this->boards->create($community->id, $category->id, 'general', 'General', 'General board', 1, false, null, $timestamp);

        $memberRole = $this->roles->findBySlug('member');
        $user = $this->users->create('alice', 'Alice', password_hash('password', PASSWORD_BCRYPT), $memberRole->id, $timestamp);

        $thread = $this->threads->create(
            communityId: $community->id,
            boardId: $board->id,
            title: 'Welcome to the plaza',
            authorId: $user->id,
            isSticky: false,
            isLocked: false,
            isAnnouncement: false,
            timestamp: $timestamp,
        );

        $this->posts->create(
            communityId: $community->id,
            threadId: $thread->id,
            authorId: $user->id,
            bodyRaw: 'Hello world with bbcode >>1',
            bodyParsed: null,
            signatureSnapshot: null,
            timestamp: $timestamp,
        );

        return [$community, $board, $thread, $user];
    }

    public function testSearchFindsThreadsAndPosts(): void
    {
        [$community, $board] = $this->seed();

        $threads = $this->search->searchThreads($community->id, null, null, 'welcome');
        $posts = $this->search->searchPosts($community->id, null, null, 'hello');

        $this->assertNotEmpty($threads);
        $this->assertSame('Welcome to the plaza', $threads[0]['title']);

        $this->assertNotEmpty($posts);
        $this->assertSame('Welcome to the plaza', $posts[0]['thread_title']);
    }

    public function testSearchFiltersByBoard(): void
    {
        [$community, $board] = $this->seed();

        $otherBoard = $this->boards->create($community->id, $board->categoryId, 'other', 'Other', 'Other board', 2, false, null, time());

        $threads = $this->search->searchThreads($community->id, $otherBoard->id, null, 'welcome');
        $this->assertSame([], $threads);
    }

    public function testSearchSanitizesDangerousQuery(): void
    {
        [$community] = $this->seed();

        $threads = $this->search->searchThreads($community->id, null, null, '>>1');
        $this->assertSame([], $threads);
    }
}
