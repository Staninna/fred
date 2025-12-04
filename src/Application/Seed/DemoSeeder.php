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
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Application\Content\BbcodeParser;

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
        private ProfileRepository $profiles,
        private Generator $faker,
        private int $boardCount = 4,
        private int $threadsPerBoard = 3,
        private int $postsPerThread = 5,
        private int $userCount = 6,
        private ?BbcodeParser $parser = null,
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

        $users[] = $this->ensureUser('stan', 's t a n i n n a', 'stan', $roleId, $timestamp);
        $users[] = $this->ensureUser('demo', 'Demo User', 'password', $roleId, $timestamp);

        for ($i = 1; $i < $this->userCount; $i++) {
            $username = 'member' . $i;
            $existing = $this->users->findByUsername($username);
            if ($existing !== null) {
                $users[] = $existing;
                continue;
            }

            $users[] = $this->ensureUser(
                $username,
                $this->faker->name(),
                'password',
                $roleId,
                $timestamp - random_int(0, 10_000),
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
                title: $this->threadTitle($boardSlug, $i),
                authorId: $author->id,
                isSticky: $isGeneral && $i === 0,
                isLocked: $lockThread && $i === ($toCreate - 1),
                isAnnouncement: $isGeneral && $i === 0,
                timestamp: $timestamp - random_int(0, 20_000),
            );

            for ($p = 0; $p < $this->postsPerThread; $p++) {
                $postAuthor = $users[array_rand($users)];
                $body = $this->postBody($boardSlug, $i, $p);
                $this->posts->create(
                    communityId: $communityId,
                    threadId: $thread->id,
                    authorId: $postAuthor->id,
                    bodyRaw: $body,
                    bodyParsed: $this->parser()?->parse($body),
                    signatureSnapshot: null,
                    timestamp: $timestamp - random_int(0, 20_000),
                );
            }
        }
    }

    private function parser(): BbcodeParser
    {
        return $this->parser ?? new BbcodeParser();
    }

    private function threadTitle(string $boardSlug, int $index): string
    {
        if ($boardSlug === 'general' && $index === 0) {
            return 'Welcome to the plaza';
        }

        return $this->faker->sentence(5);
    }

    private function postBody(string $boardSlug, int $threadIndex, int $postIndex): string
    {
        if ($boardSlug === 'general' && $threadIndex === 0 && $postIndex === 0) {
            return "[b]Welcome[/b] to the demo!\n\n"
                . "Try replying with BBCode like [i]italics[/i], [code]print \"hi\";[/code], or [url=https://example.com]links[/url].\n"
                . "[quote]You can also nest quotes and codes[/quote]\n"
                . "Jump to this post with >>1.";
        }

        if ($boardSlug === 'general' && $threadIndex === 0 && $postIndex === 1) {
            return "Quoting works too:\n>>1\nWhat's your favorite retro machine?";
        }

        if ($boardSlug === 'trading-post') {
            return "Listing item: [b]" . $this->faker->words(3, true) . "[/b]\n"
                . "Condition: [i]" . $this->faker->word() . "[/i]\n"
                . "Details: [code]" . $this->faker->sentence(6) . "[/code]\n"
                . "Price: " . $this->faker->numberBetween(20, 300) . " credits.\n"
                . "More pics: [url=https://example.com/" . $this->faker->slug() . "]link[/url]";
        }

        if ($boardSlug === 'help-desk') {
            return "[quote]" . $this->faker->sentence(8) . "[/quote]\n"
                . "Steps tried:\n"
                . "[code]" . $this->faker->sentence(5) . "[/code]\n"
                . "Any ideas?";
        }

        return $this->faker->paragraph(3);
    }

    private function ensureUser(string $username, string $displayName, string $password, int $roleId, int $timestamp)
    {
        $user = $this->users->findByUsername($username);
        if ($user === null) {
            $user = $this->users->create(
                username: $username,
                displayName: $displayName,
                passwordHash: password_hash($password, PASSWORD_BCRYPT),
                roleId: $roleId,
                createdAt: $timestamp,
            );
        }
        $profile = $this->profiles->findByUserId($user->id);
        if ($profile === null) {
            $this->profiles->create(
                userId: $user->id,
                bio: '',
                location: '',
                website: '',
                signatureRaw: '',
                signatureParsed: '',
                avatarPath: '',
                timestamp: $timestamp,
            );
        }

        return $user;
    }
}
