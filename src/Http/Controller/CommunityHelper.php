<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;

use function array_values;
use function ctype_digit;
use function preg_replace;
use function strtolower;
use function trim;

/**
 * Shared helpers for community-aware controllers.
 */
final readonly class CommunityHelper
{
    public function __construct(
        private CommunityRepository $communities,
        private CategoryRepository $categories,
        private BoardRepository $boards,
    ) {
    }

    public function resolveCommunity(?string $slug): ?Community
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return $this->communities->findBySlug($slug);
    }

    public function resolveBoard(Community $community, string $boardSlug): ?Board
    {
        $board = $this->boards->findBySlug($community->id, $boardSlug);
        if ($board === null && ctype_digit($boardSlug)) {
            $board = $this->boards->findById((int) $boardSlug);

            if ($board !== null && $board->communityId !== $community->id) {
                return null;
            }
        }

        return $board;
    }

    /**
     * @return array{
     *     categories: Category[],
     *     boards: Board[],
     *     boardsByCategory: array<int, Board[]>
     * }
     */
    public function structureForCommunity(Community $community): array
    {
        $categories = $this->categories->listByCommunityId($community->id);
        $boards = $this->boards->listByCommunityId($community->id);

        return [
            'categories' => $categories,
            'boards' => $boards,
            'boardsByCategory' => $this->groupBoards($boards),
        ];
    }

    /**
     * @param Community[]|null $communities
     * @param Category[] $categories
     * @param array<int, Board[]> $boardsByCategory
     */
    public function navSections(?Community $current, array $categories, array $boardsByCategory, ?array $communities = null): array
    {
        $communities ??= $this->communities->all();

        $communityLinks = [];
        foreach ($communities as $community) {
            $communityLinks[] = [
                'label' => $community->name,
                'href' => '/c/' . $community->slug,
            ];
        }

        $boardLinks = [];
        foreach ($categories as $category) {
            $boardLinks[] = [
                'label' => $category->name,
                'href' => '#',
            ];

            foreach ($boardsByCategory[$category->id] ?? [] as $board) {
                $boardLinks[] = [
                    'label' => '↳ ' . $board->name,
                    'href' => '/c/' . ($current?->slug ?? '') . '/b/' . $board->slug,
                ];
            }
        }

        return [
            [
                'title' => 'Communities',
                'items' => $communityLinks,
            ],
            [
                'title' => 'Boards',
                'items' => $boardLinks === [] ? [['label' => 'No boards yet', 'href' => '#']] : $boardLinks,
            ],
        ];
    }

    /** @param Board[] $boards @return array<int, Board[]> */
    public function groupBoards(array $boards): array
    {
        $grouped = [];
        foreach ($boards as $board) {
            $grouped[$board->categoryId][] = $board;
        }

        foreach ($grouped as $categoryId => $items) {
            $grouped[$categoryId] = array_values($items);
        }

        return $grouped;
    }

    public function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';

        return trim((string) $slug, '-');
    }
}
