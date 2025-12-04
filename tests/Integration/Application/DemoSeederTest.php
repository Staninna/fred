<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use Fred\Application\Seed\DemoSeeder;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\UserRepository;
use Tests\TestCase;

final class DemoSeederTest extends TestCase
{
    public function testSeedsDemoDataIdempotently(): void
    {
        $pdo = $this->makeMigratedPdo();

        $seeder = new DemoSeeder(
            roles: new RoleRepository($pdo),
            users: new UserRepository($pdo),
            communities: new CommunityRepository($pdo),
            categories: new CategoryRepository($pdo),
            boards: new BoardRepository($pdo),
            threads: new ThreadRepository($pdo),
            posts: new PostRepository($pdo),
        );

        $first = $seeder->seed();
        $second = $seeder->seed();

        $user = (new UserRepository($pdo))->findByUsername('demo');
        $community = (new CommunityRepository($pdo))->findBySlug('demo');
        $board = (new BoardRepository($pdo))->findBySlug($community?->id ?? 0, 'general');

        $this->assertNotNull($user);
        $this->assertNotNull($community);
        $this->assertNotNull($board);
        $this->assertSame($first['user_id'], $second['user_id']);
        $this->assertSame($first['community_id'], $second['community_id']);
        $this->assertSame($first['board_id'], $second['board_id']);

        $threads = (new ThreadRepository($pdo))->listByBoardId($board->id);
        $this->assertGreaterThanOrEqual(1, count($threads));

        $posts = (new PostRepository($pdo))->listByThreadId($threads[0]->id);
        $this->assertGreaterThanOrEqual(1, count($posts));
    }
}
