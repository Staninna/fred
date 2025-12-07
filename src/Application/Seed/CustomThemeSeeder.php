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
            description: 'Sample community that showcases custom CSS at community and board level.',
            customCss: <<<CSS
:root {
  --bg: #d7e8f5;
  --link: #8a2be2;
  --accent: #ffd27f;
}
.banner { letter-spacing: 0.5px; }
.section-table th { background: #f0eaff; }
body { background-image: linear-gradient(180deg, #eef3ff, #ffffff); }
table.section-table { border-radius: 4px; }
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
            /* Board 1: soft green */
            '.page-frame { border: 3px solid #6a8f6b; box-shadow: none; } .section-table th { background:#e8f5e9; } .button { background:#e0f2e9; border-color:#6a8f6b; }',
            /* Board 2: retro terminal */
            'body { background:#0f111a; color:#c1f4c7; } .page-frame { border:2px solid #6cf36c; background:#12141f; } a{color:#6cf36c;} .section-table td { background:#181b26; color:#c1f4c7; }',
            /* Board 3: sunset gradient */
            '.banner { background: linear-gradient(90deg,#ff7e5f,#feb47b); color:#1c0c05; } .page-frame { border:2px solid #ff7e5f; } .section-table th { background:#ffe8d9; }',
            /* Board 4: grayscale newspaper */
            'body { background:#f5f5f5; color:#222; } .page-frame { border:1px solid #444; box-shadow:none; } .section-table td { background:#fafafa; border-color:#ccc; } a { color:#111; text-decoration:none; border-bottom:1px dotted #555; }',
            /* Board 5: bold purple/teal */
            '.banner { background:#2d004d; color:#d7f9ff; } .page-frame { border:3px solid #00c2b2; } .button { background:linear-gradient(#00c2b2,#008f86); color:#0a0a0a; border-color:#005f58; }',
        ];

        for ($i = 0; $i < 5; $i++) {
            $name = 'Custom Board ' . ($i + 1);
            $slug = 'custom-board-' . ($i + 1);
            $this->boards->create(
                communityId: $community->id,
                categoryId: $category->id,
                slug: $slug,
                name: $name,
                description: 'Uses custom CSS snippet #' . ($i + 1),
                position: $i + 1,
                isLocked: false,
                customCss: $boardCssSnippets[$i] ?? null,
                timestamp: $timestamp,
            );
        }
    }
}
