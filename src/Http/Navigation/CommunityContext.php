<?php

declare(strict_types=1);

namespace Fred\Http\Navigation;

use function array_values;
use function ctype_digit;

use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;

use function preg_replace;
use function strtolower;
use function trim;

/**
 * Request-scoped community lookup, structure, and navigation builder with simple caching.
 */
final class CommunityContext
{
    /** @var array<string, Community> */
    private array $communityCache = [];
    /** @var array<int, Board> */
    private array $boardCache = [];
    /** @var array<int, array{categories: Category[], boards: Board[], boardsByCategory: array<int, Board[]>}> */
    private array $structureCache = [];
    /** @var Community[]|null */
    private ?array $allCommunitiesCache = null;

    public function __construct(
        private readonly CommunityRepository $communities,
        private readonly CategoryRepository $categories,
        private readonly BoardRepository $boards,
    ) {
    }

    public function resolveCommunity(?string $slug): ?Community
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        if (isset($this->communityCache[$slug])) {
            return $this->communityCache[$slug];
        }

        $community = $this->communities->findBySlug($slug);

        if ($community !== null) {
            $this->communityCache[$slug] = $community;
        }

        return $community;
    }

    public function resolveBoard(Community $community, string $boardSlugOrId): ?Board
    {
        // Try slug first.
        $board = $this->boards->findBySlug($community->id, $boardSlugOrId);

        // Fallback to numeric id for legacy links.
        if ($board === null && ctype_digit($boardSlugOrId)) {
            $boardId = (int) $boardSlugOrId;
            $board = $this->boards->findById($boardId);

            if ($board !== null && $board->communityId !== $community->id) {
                $board = null;
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
        if (isset($this->structureCache[$community->id])) {
            return $this->structureCache[$community->id];
        }

        $categories = $this->categories->listByCommunityId($community->id);
        $boards = $this->boards->listByCommunityId($community->id);
        $boardsByCategory = $this->groupBoards($boards);

        return $this->structureCache[$community->id] = [
            'categories' => $categories,
            'boards' => $boards,
            'boardsByCategory' => $boardsByCategory,
        ];
    }

    /**
     * @param Community[]|null $communities
     * @param Category[] $categories
     * @param array<int, Board[]> $boardsByCategory
     */
    public function navSections(?Community $current, array $categories, array $boardsByCategory, ?array $communities = null): array
    {
        $communities ??= $this->allCommunities();

        $communityLinks = [];

        foreach ($communities as $community) {
            $communityLinks[] = [
                'label' => $community->name,
                'href' => '/c/' . $community->slug,
            ];
        }

        $boardLinks = [];
        $communitySlug = $current?->slug ?? '';

        if ($communitySlug !== '') {
            $boardLinks[] = [
                'label' => 'Search',
                'href' => '/c/' . $communitySlug . '/search',
            ];
            $boardLinks[] = [
                'label' => 'About',
                'href' => '/c/' . $communitySlug . '/about',
            ];
        }

        foreach ($categories as $category) {
            $boardLinks[] = [
                'label' => $category->name,
                'href' => $communitySlug !== '' ? '/c/' . $communitySlug . '#category-' . $category->id : '#',
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

    public function navForCommunity(?Community $community = null): array
    {
        if ($community === null) {
            $communities = $this->allCommunities();

            return [
                [
                    'title' => 'Communities',
                    'items' => array_map(static fn (Community $c) => [
                        'label' => $c->name,
                        'href' => '/c/' . $c->slug,
                    ], $communities),
                ],
            ];
        }

        $structure = $this->structureForCommunity($community);

        return $this->navSections($community, $structure['categories'], $structure['boardsByCategory']);
    }

    public function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';

        return trim((string) $slug, '-');
    }

    /** @param Board[] $boards @return array<int, Board[]> */
    private function groupBoards(array $boards): array
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

    /** @return Community[] */
    private function allCommunities(): array
    {
        if ($this->allCommunitiesCache !== null) {
            return $this->allCommunitiesCache;
        }

        return $this->allCommunitiesCache = $this->communities->all();
    }
}
