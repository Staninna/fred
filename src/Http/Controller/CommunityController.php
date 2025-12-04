<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\View\ViewRenderer;

use function array_values;
use function strtolower;
use function trim;

final readonly class CommunityController
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

    public function index(Request $request, array $errors = [], array $old = []): Response
    {
        $communities = $this->communities->all();

        $body = $this->view->render('pages/community/index.php', [
            'pageTitle' => 'Communities',
            'communities' => $communities,
            'errors' => $errors,
            'old' => $old,
            'activePath' => $request->path,
            'navSections' => $this->navSections(null, $communities),
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
        ]);

        return new Response(
            status: $errors === [] ? 200 : 422,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    public function store(Request $request): Response
    {
        $name = trim((string) ($request->body['name'] ?? ''));
        $slug = trim((string) ($request->body['slug'] ?? ''));
        $description = trim((string) ($request->body['description'] ?? ''));

        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required.';
        }

        $slug = $slug === '' ? $this->slugify($name) : $this->slugify($slug);
        if ($slug === '') {
            $errors[] = 'Slug is required.';
        }

        if ($errors !== []) {
            return $this->index($request, $errors, [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]);
        }

        if ($this->communities->findBySlug($slug) !== null) {
            return $this->index($request, ['Slug is already taken.'], [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]);
        }

        $timestamp = time();
        $community = $this->communities->create($slug, $name, $description, null, $timestamp);

        return Response::redirect('/c/' . $community->slug);
    }

    public function show(Request $request): Response
    {
        $community = $this->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return new Response(
                status: 404,
                headers: ['Content-Type' => 'text/html; charset=utf-8'],
                body: '<h1>Community not found</h1>',
            );
        }

        $categories = $this->categories->listByCommunityId($community->id);
        $boards = $this->boards->listByCommunityId($community->id);
        $boardsByCategory = $this->groupBoards($boards);

        $body = $this->view->render('pages/community/show.php', [
            'pageTitle' => $community->name,
            'community' => $community,
            'categories' => $categories,
            'boardsByCategory' => $boardsByCategory,
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'activePath' => $request->path,
            'navSections' => $this->navSections($community, $this->communities->all(), $boardsByCategory, $categories),
        ]);

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    private function groupBoards(array $boards): array
    {
        $grouped = [];
        /** @var Board $board */
        foreach ($boards as $board) {
            $grouped[$board->categoryId][] = $board;
        }

        foreach ($grouped as $categoryId => $items) {
            $grouped[$categoryId] = array_values($items);
        }

        return $grouped;
    }

    private function navSections(?Community $current, array $communities, array $boardsByCategory = [], array $categories = []): array
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

    private function resolveCommunity(?string $slug): ?Community
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return $this->communities->findBySlug($slug);
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';
        $slug = trim((string) $slug, '-');

        return $slug;
    }
}
