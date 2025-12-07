<?php

declare(strict_types=1);

namespace Fred\Application\Seed;

use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;

final readonly class CustomThemeSeeder
{
    public function __construct(
        private CommunityRepository $communities,
        private CategoryRepository $categories,
        private BoardRepository $boards,
    ) {
    }

    public function seed(): void
    {
        $existing = $this->communities->findBySlug('themed');
        if ($existing !== null) {
            return;
        }

        $timestamp = time();
        $community = $this->communities->create(
            slug: 'themed',
            name: 'Custom Styled Plaza',
            description: 'Sample community with custom CSS on boards.',
            customCss: <<<CSS
:root {
  --bg: #d7e8f5;
  --link: #8a2be2;
  --accent: #ffd27f;
}
.banner { letter-spacing: 0.5px; }
.section-table th { background: #f0eaff; }
CSS,
            timestamp: $timestamp,
        );

        $category = $this->categories->create(
            communityId: $community->id,
            name: 'Showroom',
            position: 1,
            timestamp: $timestamp,
        );

        $boardCssSnippets = [
            '.page-frame { border-radius: 8px; }',
            '.post-table .author-cell { background: #f9f1d9; }',
            '.button { border-radius: 6px; }',
            '.section-table td { border-color: #d3c7ff; }',
            'body { background-image: linear-gradient(180deg, #eef3ff, #ffffff); }',
        ];

        for ($i = 0; $i < 5; $i++) {
            $name = 'Board ' . ($i + 1);
            $slug = 'board-' . ($i + 1);
            $this->boards->create(
                communityId: $community->id,
                categoryId: $category->id,
                slug: $slug,
                name: $name,
                description: 'Custom CSS sample ' . ($i + 1),
                position: $i + 1,
                isLocked: false,
                customCss: $boardCssSnippets[$i] ?? null,
                timestamp: $timestamp,
            );
        }
    }
}
