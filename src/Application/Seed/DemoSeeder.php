<?php

declare(strict_types=1);

namespace Fred\Application\Seed;

use Faker\Generator;
use Fred\Application\Content\BbcodeParser;
use Fred\Application\Content\EmoticonSet;
use Fred\Application\Content\MentionService;
use Fred\Domain\Auth\User;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityModeratorRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\ReactionRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\UserRepository;
use Random\RandomException;

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
        private CommunityModeratorRepository $communityModerators,
        private ReactionRepository $reactions,
        private MentionService $mentions,
        private Generator $faker,
        private AppConfig $config,
        private int $boardCount = 4,
        private int $threadsPerBoard = 3,
        private int $postsPerThread = 5,
        private int $userCount = 6,
        private ?BbcodeParser $parser = null,
        private ?ProgressTracker $progress = null,
        private ?EmoticonSet $emoticons = null,
    ) {
    }

    /**
     * @throws RandomException
     */
    public function seed(): array
    {
        $this->log('Seeding demo data');
        $this->faker->seed(1234);
        $now = time();
        $this->faker->unique(true);

        // Start transaction for massive performance boost
        $this->pdo()->beginTransaction();

        try {
            $this->roles->ensureDefaultRoles();
            $memberRole = $this->roles->findBySlug('member');
            $moderatorRole = $this->roles->findBySlug('moderator');
            $adminRole = $this->roles->findBySlug('admin');

            if ($memberRole === null || $moderatorRole === null || $adminRole === null) {
                throw new \RuntimeException('Core roles are missing.');
            }
            $this->log('Core roles ensured');

            $users = $this->seedUsers($memberRole->id, $moderatorRole->id, $adminRole->id, $now);
            $this->log('Users ready: ' . \count($users));

            $communities = $this->seedCommunities($now);
            $boardIds = [];
            $this->log('Communities ready: ' . \count($communities));

            $this->ensureProfilesPerCommunityBatch($users, $communities, $now);
            $this->log('Profiles ensured across communities');

            foreach ($communities as $community) {
                $boardIds = array_merge($boardIds, $this->seedCommunityContent($community, $users, $now));
                $this->assignModerators($community->id, $users, $now, $moderatorRole->id);
                $this->log('Moderators assigned for community ' . $community->slug);
            }

            $this->pdo()->commit();
            $this->log('Demo seeding complete');

            return [
                'community_ids' => array_map(static fn ($c) => $c->id, $communities),
                'user_ids' => array_map(static fn ($u) => $u->id, $users),
                'board_ids' => $boardIds,
            ];
        } catch (\Throwable $e) {
            $this->pdo()->rollBack();
            throw $e;
        }
    }

    private function pdo(): \PDO
    {
        return $this->posts->pdo();
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

    /**
     * @throws RandomException
     */
    private function seedUsers(int $memberRoleId, int $moderatorRoleId, int $adminRoleId, int $timestamp): array
    {
        $users = [];

        $users[] = $this->ensureUser('stan', 's t a n i n n a', 'stan', $adminRoleId, $timestamp);
        $users[] = $this->ensureUser('mod', 'Moderator', 'mod', $moderatorRoleId, $timestamp);
        $users[] = $this->ensureUser('user', 'Regular User', 'user', $memberRoleId, $timestamp);
        $users[] = $this->ensureUser('demo', 'Demo User', 'password', $memberRoleId, $timestamp);

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
                $memberRoleId,
                $timestamp - random_int(0, 10_000),
            );
        }

        return $users;
    }

    private function assignModerators(int $communityId, array $users, int $timestamp, int $moderatorRoleId): void
    {
        foreach ($users as $user) {
            if (\in_array($user->username, ['mod'], true)) {
                $this->communityModerators->assign($communityId, $user->id, $timestamp);
            }
        }
    }

    private function seedCommunities(int $timestamp): array
    {
        $communities = [];

        foreach ([
            [
                'slug' => 'demo',
                'name' => 'Demo Community',
                'description' => 'Sample space to explore Fred with seeded threads, posts, and moderator tools.',
            ],
            [
                'slug' => 'retro',
                'name' => 'Retro Arcade',
                'description' => 'Vintage computing and retro gaming hangout: share builds, CRT tips, and high scores.',
            ],
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

    /**
     * @throws RandomException
     */
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
                $this->seedThreadsAndPosts($community->id, $community->slug, $board->id, $board->slug, $users, $timestamp);
            }
        }

        return $boardIds;
    }

    /**
     * @throws RandomException
     */
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

    /**
     * @throws RandomException
     */
    private function seedThreadsAndPosts(int $communityId, string $communitySlug, int $boardId, string $boardSlug, array $users, int $timestamp): void
    {
        $existingThreads = $this->threads->listByBoardId($boardId);
        $toCreate = max(0, $this->threadsPerBoard - \count($existingThreads));

        if ($toCreate === 0) {
            return;
        }

        $isGeneral = $boardSlug === 'general';
        $lockThread = $boardSlug === 'trading-post';

        // Build thread data for batch insert
        $threadsData = [];

        for ($i = 0; $i < $toCreate; $i++) {
            $author = $users[array_rand($users)];
            $threadsData[] = [
                'communityId' => $communityId,
                'boardId' => $boardId,
                'title' => $this->threadTitle($boardSlug, $i),
                'authorId' => $author->id,
                'isSticky' => $isGeneral && $i === 0,
                'isLocked' => $lockThread && $i === ($toCreate - 1),
                'isAnnouncement' => $isGeneral && $i === 0,
                'timestamp' => $timestamp - random_int(0, 20_000),
            ];
        }

        // Batch insert threads (returns last inserted ID), derive the full range
        $lastThreadId = $this->threads->batchInsert($threadsData);
        $firstThreadId = $lastThreadId - ($toCreate - 1);
        $actualThreadIds = range($firstThreadId, $lastThreadId);

        // Build post data for batch insert
        $postsData = [];
        $postMetadata = []; // Track author and body for each post

        for ($i = 0; $i < $toCreate; $i++) {
            $threadId = $actualThreadIds[$i];

            for ($p = 0; $p < $this->postsPerThread; $p++) {
                $postAuthor = $users[array_rand($users)];
                $body = $this->postBody($boardSlug, $i, $p);

                $postsData[] = [
                    'communityId' => $communityId,
                    'threadId' => $threadId,
                    'authorId' => $postAuthor->id,
                    'bodyRaw' => $body,
                    'bodyParsed' => $this->parser()?->parse($body, $communitySlug),
                    'signatureSnapshot' => null,
                    'timestamp' => $timestamp - random_int(0, 20_000),
                ];

                $postMetadata[] = [
                    'authorId' => $postAuthor->id,
                    'body' => $body,
                ];
            }
        }

        // Batch insert all posts (returns last inserted ID), derive the full range
        $lastPostId = $this->posts->batchInsert($postsData);
        $firstPostId = $lastPostId - (\count($postsData) - 1);
        $actualPostIds = range($firstPostId, $lastPostId);

        $this->log('Inserted ' . \count($postsData) . ' posts, IDs from ' . $firstPostId . ' to ' . $lastPostId);

        // Now build mentions and reactions with actual post IDs
        $mentionsData = [];
        $reactionsData = [];

        for ($idx = 0; $idx < \count($postMetadata); $idx++) {
            $postId = $actualPostIds[$idx];
            $meta = $postMetadata[$idx];

            // Extract and collect mentions
            $mentioned = $this->extractMentions($meta['body'], $users);

            foreach ($mentioned as $mentionedUser) {
                $mentionsData[] = [
                    'communityId' => $communityId,
                    'postId' => $postId,
                    'mentionedUserId' => $mentionedUser->id,
                    'mentionedByUserId' => $meta['authorId'],
                    'createdAt' => $timestamp,
                ];
            }

            // Build reactions with actual post ID
            $codes = $this->emoticons()?->codes() ?? [];

            if ($codes !== []) {
                $targetCount = $this->weightedReactionCount();

                if ($targetCount > 0) {
                    $userPool = $users;
                    shuffle($userPool);

                    for ($i = 0; $i < $targetCount; $i++) {
                        $code = $codes[array_rand($codes)];
                        $user = $userPool[$i % \count($userPool)];
                        $reactionsData[] = [
                            'communityId' => $communityId,
                            'postId' => $postId,
                            'userId' => $user->id,
                            'emoticon' => $code,
                            'timestamp' => $timestamp,
                        ];
                    }
                }
            }
        }

        // Batch insert mentions and reactions
        if ($mentionsData !== []) {
            $this->log('Batch inserting ' . \count($mentionsData) . ' mentions');
            $this->mentions->batchInsertNotifications($mentionsData);
        }

        if ($reactionsData !== []) {
            $this->log('Batch inserting ' . \count($reactionsData) . ' reactions (post IDs from ' . min(array_column($reactionsData, 'postId')) . ' to ' . max(array_column($reactionsData, 'postId')) . ')');
            $this->reactions->batchInsertReactions($reactionsData);
        }

        $this->log('Threads/posts seeded for board ' . $boardSlug . ': +' . $toCreate . ' threads');
    }

    private function extractMentions(string $body, array $users): array
    {
        $mentioned = [];

        if (preg_match_all('/@(\w+)/', $body, $matches)) {
            foreach ($matches[1] as $username) {
                foreach ($users as $user) {
                    if ($user->username === $username) {
                        $mentioned[] = $user;
                        break;
                    }
                }
            }
        }

        return $mentioned;
    }



    private function parser(): BbcodeParser
    {
        return $this->parser ?? new BbcodeParser();
    }


    private function weightedReactionCount(): int
    {
        // 0 most common, 10 rare
        $weights = [
            0 => 30,
            1 => 20,
            2 => 15,
            3 => 10,
            4 => 7,
            5 => 6,
            6 => 5,
            7 => 3,
            8 => 2,
            9 => 1,
            10 => 1,
        ];

        $total = array_sum($weights);
        $pick = random_int(1, $total);
        $running = 0;

        foreach ($weights as $value => $weight) {
            $running += $weight;

            if ($pick <= $running) {
                return $value;
            }
        }

        return 0;
    }

    private function emoticons(): EmoticonSet
    {
        return $this->emoticons ?? new EmoticonSet($this->config);
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
                . 'Jump to this post with >>1 and mention @mod for help.';
        }

        if ($boardSlug === 'general' && $threadIndex === 0 && $postIndex === 1) {
            return "Quoting works too:\n>>1\nWhat's your favorite retro machine?";
        }

        if ($boardSlug === 'general' && $threadIndex === 0 && $postIndex === 2) {
            return "Check this map of our next meetup spot: https://maps.google.com/?q=Eiffel+Tower\n"
                . "And a playlist I'm looping: https://www.youtube.com/watch?v=dQw4w9WgXcQ\n"
                . 'Also pinging @stan and @user to chime in.';
        }

        if ($boardSlug === 'trading-post') {
            $image = 'https://picsum.photos/seed/' . $this->faker->word() . '/640/360';

            return 'Listing item: [b]' . $this->faker->words(3, true) . "[/b]\n"
                . 'Condition: [i]' . $this->faker->word() . "[/i]\n"
                . "Specs:\n- CPU: " . $this->faker->word() . "\n- RAM: " . $this->faker->numberBetween(4, 64) . " GB\n- Notes: " . $this->faker->sentence(6) . "\n"
                . 'Gallery: [img]' . $image . "[/img]\n"
                . 'More pics: [url=https://example.com/' . $this->faker->slug() . ']link[/url]\n'
                . 'Price: ' . $this->faker->numberBetween(20, 300) . ' credits.';
        }

        if ($boardSlug === 'help-desk') {
            return '[quote]' . $this->faker->sentence(10) . "[/quote]\n"
                . "What I tried:\n"
                . "[list]\n[*]Rebooted twice\n[*]Cleared cache\n[*]Checked cables\n[/list]\n"
                . "Logs:\n[code]" . $this->faker->sentence(8) . "\n" . $this->faker->sentence(8) . "[/code]\n"
                . "Environment: PHP 8.3, SQLite\n"
                . 'Any ideas @mod or @stan?';
        }

        // Variety pool for other boards
        $templates = [
            fn () => 'Show-and-tell: [b]' . $this->faker->words(2, true) . "[/b]\n"
                . 'Setup photo: [img]https://picsum.photos/seed/' . $this->faker->word() . "/800/450[/img]\n"
                . "Parts list:\n[list]\n[*]" . $this->faker->word() . "\n[*]" . $this->faker->word() . "\n[*]" . $this->faker->word() . "\n[/list]\n"
                . 'Benchmarks: [code]fps=' . $this->faker->numberBetween(30, 240) . '[/code]',
            fn () => 'Quick tip: use [code]' . $this->faker->word() . " --help[/code] to debug.\n"
                . 'Reference doc: [url=https://example.com/docs/' . $this->faker->slug() . "]link[/url]\n"
                . 'Also @user might know.',
            fn () => "Weekend project log:\n" . $this->faker->paragraph(2) . "\n"
                . 'Playlist: https://open.spotify.com/track/' . $this->faker->sha1() . "\n"
                . 'Map: https://maps.google.com/?q=' . urlencode($this->faker->city()),
            fn () => "Code drop:\n[code]\n<?php\nfunction ping() {\n    return '" . $this->faker->word() . "';\n}\n[/code]\n"
                . 'Does this look right?',
            fn () => "Poll: favorite distro?\n[list]\n[*]Debian\n[*]Arch\n[*]Fedora\n[*]macOS\n[/list]\n"
                . 'Reply with >>' . $this->faker->numberBetween(1, 10) . ' to vote.',
            fn () => "Image-heavy post:\n[img]https://picsum.photos/seed/" . $this->faker->word() . "/600/400[/img]\n"
                . 'Second view: [img]https://picsum.photos/seed/' . $this->faker->word() . '/600/400[/img]',
            fn () => "Release notes draft:\n[code]\n## v" . $this->faker->randomFloat(2, 0, 5) . "\n- Added " . $this->faker->word() . "\n- Fixed " . $this->faker->word() . "\n[/code]\n"
                . 'Changelog preview?',
            fn () => "Gallery dump:\n" . implode("\n", array_map(fn () => '[img]https://picsum.photos/seed/' . $this->faker->word() . '/500/320[/img]', range(1, 3))) . "\n"
                . 'Which angle is best?',
            fn () => 'Memory lane: [quote]' . $this->faker->sentence(12) . "[/quote]\n"
                . 'Old flyer: [img]https://picsum.photos/seed/retro' . $this->faker->numberBetween(1, 999) . '/720/480[/img]',
            fn () => "Micro how-to:\n[list]\n[*]Clone repo\n[*]Run [code]composer install[/code]\n[*]Launch [code]php -S localhost:8000[/code]\n[/list]\n"
                . 'Attached log: [code]' . $this->faker->sentence(6) . '[/code]',
            fn () => "Bug repro steps:\n1. Open profile\n2. Click avatar\n3. Upload image\nObserved: " . $this->faker->sentence(8) . "\nExpected: " . $this->faker->sentence(6) . "\n"
                . 'Screenshot: [img]https://picsum.photos/seed/bug' . $this->faker->numberBetween(1, 9999) . "/640/360[/img]\n"
                . 'Tagging @mod.',
            fn () => "Data table:\n[code]\n| User | Score |\n| --- | --- |\n| " . $this->faker->userName() . ' | ' . $this->faker->numberBetween(1, 9999) . " |\n| " . $this->faker->userName() . ' | ' . $this->faker->numberBetween(1, 9999) . " |\n[/code]\n"
                . 'CSV: [url=https://example.com/' . $this->faker->slug() . '.csv]download[/url]',
            fn () => "Theme idea:\n[code]\n:root {\n  --accent: #" . substr($this->faker->hexColor(), 1) . ";\n  --bg: #" . substr($this->faker->hexColor(), 1) . ";\n}\n[/code]\n"
                . 'Preview: [img]https://picsum.photos/seed/theme' . $this->faker->numberBetween(1, 9999) . '/640/360[/img]',
            fn () => "API trace:\n[code]{\\n  'status': '" . $this->faker->word() . "',\\n  'latency_ms': " . $this->faker->numberBetween(20, 900) . "\\n}[/code]\n"
                . 'Any perf tips?',
            fn () => 'Daily question: ' . $this->faker->sentence(9) . "\n"
                . 'Link: [url=https://example.com/' . $this->faker->slug() . "]context[/url]\n"
                . 'Drop your takes below.',
        ];

        $pick = $templates[array_rand($templates)];

        return $pick();
    }

    private function ensureUser(string $username, string $displayName, string $password, int $roleId, int $timestamp): ?User
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

        return $user;
    }

    private function ensureProfilesPerCommunity(array $users, array $communities, int $timestamp): void
    {
        foreach ($users as $user) {
            foreach ($communities as $community) {
                $existing = $this->profiles->findByUserAndCommunity($user->id, $community->id);

                if ($existing !== null) {
                    continue;
                }

                $this->profiles->create(
                    userId: $user->id,
                    communityId: $community->id,
                    bio: '',
                    location: '',
                    website: '',
                    signatureRaw: '',
                    signatureParsed: '',
                    avatarPath: '',
                    timestamp: $timestamp,
                );
            }
        }
    }

    private function ensureProfilesPerCommunityBatch(array $users, array $communities, int $timestamp): void
    {
        $profiles = [];

        foreach ($users as $user) {
            foreach ($communities as $community) {
                $profiles[] = [
                    'userId' => $user->id,
                    'communityId' => $community->id,
                    'timestamp' => $timestamp,
                ];
            }
        }

        if ($profiles !== []) {
            $this->profiles->batchInsert($profiles);
        }
    }

    private function log(string $message): void
    {
        $this->progress?->log($message);
    }
}
