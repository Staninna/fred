<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ReportRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\Database\UserRepository;
use Tests\TestCase;

final class ReportFlowTest extends TestCase
{
    public function testReportLifecycle(): void
    {
        $pdo = $this->makeMigratedPdo();

        $roles = new RoleRepository($pdo);
        $roles->ensureDefaultRoles();
        $memberRole = $roles->findBySlug('member');
        self::assertNotNull($memberRole);

        $users = new UserRepository($pdo);
        $reporter = $users->create('reporter', 'Reporter', password_hash('secret', PASSWORD_BCRYPT), $memberRole->id, time());
        $author = $users->create('author', 'Author', password_hash('secret', PASSWORD_BCRYPT), $memberRole->id, time());

        $communities = new CommunityRepository($pdo);
        $community = $communities->create('main', 'Main', 'Desc', null, time());

        $categories = new CategoryRepository($pdo);
        $category = $categories->create($community->id, 'Cat', 1, time());

        $boards = new BoardRepository($pdo);
        $board = $boards->create($community->id, $category->id, 'board', 'Board', 'desc', 1, false, null, time());

        $threads = new ThreadRepository($pdo);
        $thread = $threads->create($community->id, $board->id, 'Hello', $author->id, false, false, false, time());

        $posts = new PostRepository($pdo);
        $post = $posts->create($community->id, $thread->id, $author->id, 'Body', 'Body', null, time());

        $reports = new ReportRepository($pdo);
        $report = $reports->create($community->id, $post->id, $reporter->id, 'Spam', time());

        $open = $reports->listWithContext($community->id, 'open');
        self::assertCount(1, $open);
        self::assertSame('open', $open[0]['report']->status);
        self::assertSame($thread->id, $open[0]['thread_id']);
        self::assertSame($reporter->username, $open[0]['reporter_username']);

        $reports->updateStatus($report->id, 'closed', time());

        $closed = $reports->listByCommunity($community->id, 'closed');
        self::assertCount(1, $closed);
        self::assertSame('closed', $closed[0]->status);
    }
}
