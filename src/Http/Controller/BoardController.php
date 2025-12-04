<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\View\ViewRenderer;

use function array_values;

final readonly class BoardController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private CommunityRepository $communities,
        private CategoryRepository $categories,
        private BoardRepository $boards,
    ) {
    }

    public function show(Request $request): Response
    {
        $community = $this->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound();
        }

        $boardId = (int) ($request->params['board'] ?? 0);
        $board = $this->boards->findById($boardId);
        if ($board === null || $board->communityId !== $community->id) {
            return $this->notFound();
        }

        $category = $this->categories->findById($board->categoryId);
        if ($category === null || $category->communityId !== $community->id) {
            return $this->notFound();
        }

        $categories = $this->categories->listByCommunityId($community->id);
        $boards = $this->boards->listByCommunityId($community->id);
        $boardsByCategory = $this->groupBoards($boards);
        $allCommunities = $this->communities->all();

        $body = $this->view->render('pages/board/show.php', [
            'pageTitle' => $board->name,
            'community' => $community,
            'board' => $board,
            'category' => $category,
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'activePath' => $request->path,
            'navSections' => $this->navSections($community, $allCommunities, $boardsByCategory, $categories),
        ]);

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    private function resolveCommunity(?string $slug): ?Community
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return $this->communities->findBySlug($slug);
    }

    /**
     * @param Board[] $boards
     *
     * @return array<int, Board[]>
     */
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

    private function navSections(Community $current, array $communities, array $boardsByCategory, array $categories): array
    {
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
                    'href' => '/c/' . $current->slug . '/b/' . $board->id,
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

    private function notFound(): Response
    {
        return new Response(
            status: 404,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: '<h1>Not Found</h1>',
        );
    }
}
