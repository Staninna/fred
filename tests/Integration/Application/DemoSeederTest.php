<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use Faker\Factory as FakerFactory;
use Fred\Application\Seed\DemoSeeder;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\Database\CommunityModeratorRepository;
use Fred\Infrastructure\Database\ReactionRepository;
use Fred\Application\Content\EmoticonSet;
use Fred\Infrastructure\Database\MentionNotificationRepository;
use Fred\Application\Content\MentionService;
use Fred\Infrastructure\Config\AppConfig;
use Tests\TestCase;

final class DemoSeederTest extends TestCase
{
    public function testSeedsDemoDataIdempotently(): void
    {
        $pdo = $this->makeMigratedPdo();
        $faker = FakerFactory::create();
        $faker->seed(999);
        $config = new AppConfig(
            environment: 'test',
            baseUrl: 'http://localhost',
            databasePath: ':memory:',
            uploadsPath: $this->basePath('storage/uploads'),
            logsPath: $this->basePath('storage/logs'),
            basePath: $this->basePath(),
        );
        $emoticons = new EmoticonSet($config);
        $mentionRepo = new MentionNotificationRepository($pdo);
        $userRepo = new UserRepository($pdo);

        $seeder = new DemoSeeder(
            roles: new RoleRepository($pdo),
            users: $userRepo,
            communities: new CommunityRepository($pdo),
            categories: new CategoryRepository($pdo),
            boards: new BoardRepository($pdo),
            threads: new ThreadRepository($pdo),
            posts: new PostRepository($pdo),
            profiles: new ProfileRepository($pdo),
            communityModerators: new CommunityModeratorRepository($pdo),
            reactions: new ReactionRepository($pdo),
            mentions: new MentionService($userRepo, $mentionRepo),
            faker: $faker,
            config: $config,
            boardCount: 4,
            threadsPerBoard: 3,
            postsPerThread: 4,
            userCount: 5,
            emoticons: $emoticons,
        );

        $first = $seeder->seed();
        $second = $seeder->seed();

        $userStan = (new UserRepository($pdo))->findByUsername('stan');
        $community = (new CommunityRepository($pdo))->findBySlug('demo');
        $board = (new BoardRepository($pdo))->findBySlug($community?->id ?? 0, 'general');

        $this->assertNotNull($userStan);
        $this->assertNotNull($community);
        $this->assertNotNull($board);
        $this->assertSame($first['user_ids'], $second['user_ids']);
        $this->assertSame($first['community_ids'], $second['community_ids']);
        $this->assertSame('stan', $userStan->username);
        $this->assertSame('s t a n i n n a', $userStan->displayName);

        $allBoards = (new BoardRepository($pdo))->listByCommunityId($community->id);
        $this->assertGreaterThanOrEqual(3, count($allBoards));

        $threads = (new ThreadRepository($pdo))->listByBoardId($allBoards[0]->id);
        $this->assertGreaterThanOrEqual(3, count($threads));

        $posts = (new PostRepository($pdo))->listByThreadId($threads[0]->id);
        $this->assertGreaterThanOrEqual(4, count($posts));
    }
}
