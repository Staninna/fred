<?php

declare(strict_types=1);

namespace Fred\Application\Seed;

use Faker\Generator;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\UserRepository;

final readonly class DemoSeeder
{
    public function __construct(
        private RoleRepository $roles,
        private UserRepository $users,
        private CommunityRepository $communities,
        private CategoryRepository $categories,
        private BoardRepository $boards,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private Generator $faker,
        private int $boardCount = 4,
        private int $threadsPerBoard = 3,
        private int $postsPerThread = 5,
        private int $userCount = 6,
    ) {
    }

    public function seed(): array
    {
        $this->faker->seed(1234);
        $now = time();
        $this->faker->unique(true);

        $this->roles->ensureDefaultRoles();
        $memberRole = $this->roles->findBySlug('member');
        if ($memberRole === null) {
            throw new \RuntimeException('Member role is missing.');
        }

        $users = $this->seedUsers($memberRole->id, $now);

        $communities = $this->seedCommunities($now);
        $boardIds = [];

        foreach ($communities as $community) {
            $boardIds = array_merge($boardIds, $this->seedCommunityContent($community, $users, $now));
        }

        return [
            'community_ids' => array_map(static fn ($c) => $c->id, $communities),
            'user_ids' => array_map(static fn ($u) => $u->id, $users),
            'board_ids' => $boardIds,
        ];
    }

    private function findCategory(int $communityId, string $name): ?object
    {
        foreach ($this->categories->listByCommunityId($communityId) as $category) {
            if (strcasecmp($category->name, $name) === 0) {
                return $category;
            }
        }

        return null;
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'board-' . uniqid();
    }

    private function seedUsers(int $roleId, int $timestamp): array
    {
        $users = [];

        $stan = $this->users->findByUsername('stan');
        if ($stan === null) {
            $stan = $this->users->create(
                username: 'stan',
                displayName: 's t a n i n n a',
                passwordHash: password_hash('stan', PASSWORD_BCRYPT),
                roleId: $roleId,
                createdAt: $timestamp,
            );
        }
        $users[] = $stan;

        $demo = $this->users->findByUsername('demo');
        if ($demo === null) {
            $demo = $this->users->create(
                username: 'demo',
                displayName: 'Demo User',
                passwordHash: password_hash('password', PASSWORD_BCRYPT),
                roleId: $roleId,
                createdAt: $timestamp,
            );
        }
        $users[] = $demo;

        for ($i = 1; $i < $this->userCount; $i++) {
            $username = 'member' . $i;
            $existing = $this->users->findByUsername($username);
            if ($existing !== null) {
                $users[] = $existing;
                continue;
            }

            $users[] = $this->users->create(
                username: $username,
                displayName: $this->faker->name(),
                passwordHash: password_hash('password', PASSWORD_BCRYPT),
                roleId: $roleId,
                createdAt: $timestamp - random_int(0, 10_000),
            );
        }

        return $users;
    }

    private function seedCommunities(int $timestamp): array
    {
        $communities = [];
        foreach ([
            ['slug' => 'demo', 'name' => 'Demo Community', 'description' => 'Sample space to explore Fred.'],
            ['slug' => 'retro', 'name' => 'Retro Arcade', 'description' => 'Vintage computing and retro gaming hangout.'],
        ] as $spec) {
            $community = $this->communities->findBySlug($spec['slug']);
            if ($community === null) {
                $community = $this->communities->create(
                    slug: $spec['slug'],
                    name: $spec['name'],
                    description: $spec['description'],
                    customCss: null,
                    timestamp: $timestamp,
                );
            }

            $communities[] = $community;
        }

        return $communities;
    }

    private function seedCommunityContent(object $community, array $users, int $timestamp): array
    {
        $boardIds = [];
        $categories = [
            ['name' => 'General', 'position' => 1],
            ['name' => 'Support', 'position' => 2],
            ['name' => 'Marketplace', 'position' => 3],
        ];

        foreach ($categories as $catSpec) {
            $category = $this->findCategory($community->id, $catSpec['name']) ??
                $this->categories->create($community->id, $catSpec['name'], $catSpec['position'], $timestamp);

            $boards = $this->seedBoards($community->id, $category->id, $timestamp);
            foreach ($boards as $board) {
                $boardIds[] = $board->id;
                $this->seedThreadsAndPosts($community->id, $board->id, $board->slug, $users, $timestamp);
            }
        }

        return $boardIds;
    }

    private function seedBoards(int $communityId, int $categoryId, int $timestamp): array
    {
        $boards = [];
        $seedBoards = [
            ['slug' => 'general', 'name' => 'General Chat', 'description' => 'Introduce yourself and share news.'],
            ['slug' => 'help-desk', 'name' => 'Help Desk', 'description' => 'Ask for assistance or report issues.'],
            ['slug' => 'trading-post', 'name' => 'Trading Post', 'description' => 'Buy/sell/trade with the community.'],
        ];

        foreach ($seedBoards as $index => $spec) {
            $board = $this->boards->findBySlug($communityId, $spec['slug']);
            if ($board === null) {
                $board = $this->boards->create(
                    communityId: $communityId,
                    categoryId: $categoryId,
                    slug: $spec['slug'],
                    name: $spec['name'],
                    description: $spec['description'],
                    position: $index + 1,
                    isLocked: false,
                    customCss: null,
                    timestamp: $timestamp,
                );
            }

            $boards[] = $board;
        }

        while (\count($boards) < $this->boardCount) {
            $slug = $this->slugify($this->faker->unique()->word());
            $existing = $this->boards->findBySlug($communityId, $slug);
            if ($existing !== null) {
                continue;
            }

            $boards[] = $this->boards->create(
                communityId: $communityId,
                categoryId: $categoryId,
                slug: $slug,
                name: $this->faker->words(2, true),
                description: $this->faker->sentence(8),
                position: \count($boards) + 1,
                isLocked: false,
                customCss: null,
                timestamp: $timestamp - random_int(0, 5_000),
            );
        }

        return $boards;
    }

    private function seedThreadsAndPosts(int $communityId, int $boardId, string $boardSlug, array $users, int $timestamp): void
    {
        $existingThreads = $this->threads->listByBoardId($boardId);
        $toCreate = max(0, $this->threadsPerBoard - \count($existingThreads));

        $isGeneral = $boardSlug === 'general';
        $lockThread = $boardSlug === 'trading-post';

        for ($i = 0; $i < $toCreate; $i++) {
            $author = $users[array_rand($users)];
            $thread = $this->threads->create(
                communityId: $communityId,
                boardId: $boardId,
                title: $this->faker->sentence(5),
                authorId: $author->id,
                isSticky: $isGeneral && $i === 0,
                isLocked: $lockThread && $i === ($toCreate - 1),
                isAnnouncement: $isGeneral && $i === 0,
                timestamp: $timestamp - random_int(0, 20_000),
            );

            for ($p = 0; $p < $this->postsPerThread; $p++) {
                $postAuthor = $users[array_rand($users)];
                $this->posts->create(
                    communityId: $communityId,
                    threadId: $thread->id,
                    authorId: $postAuthor->id,
                    bodyRaw: $this->faker->paragraph(3),
                    bodyParsed: null,
                    signatureSnapshot: null,
                    timestamp: $timestamp - random_int(0, 20_000),
                );
            }
        }
    }
}
