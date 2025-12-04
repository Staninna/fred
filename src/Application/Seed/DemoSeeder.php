<?php

declare(strict_types=1);

namespace Fred\Application\Seed;

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
    ) {
    }

    public function seed(): array
    {
        $now = time();

        $this->roles->ensureDefaultRoles();
        $memberRole = $this->roles->findBySlug('member');
        if ($memberRole === null) {
            throw new \RuntimeException('Member role is missing.');
        }

        $user = $this->users->findByUsername('demo');
        if ($user === null) {
            $user = $this->users->create(
                username: 'demo',
                displayName: 'Demo User',
                passwordHash: password_hash('password', PASSWORD_BCRYPT),
                roleId: $memberRole->id,
                createdAt: $now,
            );
        }

        $community = $this->communities->findBySlug('demo');
        if ($community === null) {
            $community = $this->communities->create(
                slug: 'demo',
                name: 'Demo Community',
                description: 'Sample space to explore Fred.',
                customCss: null,
                timestamp: $now,
            );
        }

        $category = $this->findCategory($community->id, 'General') ??
            $this->categories->create($community->id, 'General', 1, $now);

        $board = $this->boards->findBySlug($community->id, 'general') ??
            $this->boards->create(
                communityId: $community->id,
                categoryId: $category->id,
                slug: 'general',
                name: 'General Board',
                description: 'Introduce yourself and chat.',
                position: 1,
                isLocked: false,
                customCss: null,
                timestamp: $now,
            );

        $threads = $this->threads->listByBoardId($board->id);
        $thread = $threads[0] ?? null;

        if ($thread === null) {
            $thread = $this->threads->create(
                communityId: $community->id,
                boardId: $board->id,
                title: 'Welcome to Fred',
                authorId: $user->id,
                isSticky: true,
                isLocked: false,
                isAnnouncement: true,
                timestamp: $now,
            );

            $this->posts->create(
                communityId: $community->id,
                threadId: $thread->id,
                authorId: $user->id,
                bodyRaw: 'This is a demo thread. Feel free to post!',
                bodyParsed: null,
                signatureSnapshot: null,
                timestamp: $now,
            );
        }

        return [
            'community_id' => $community->id,
            'board_id' => $board->id,
            'user_id' => $user->id,
            'thread_id' => $thread->id,
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
}
