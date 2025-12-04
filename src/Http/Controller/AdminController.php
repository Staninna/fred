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

use function trim;

final readonly class AdminController
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

    public function structure(Request $request, array $errors = []): Response
    {
        $community = $this->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound();
        }

        $categories = $this->categories->listByCommunityId($community->id);
        $boards = $this->boards->listByCommunityId($community->id);
        $boardsByCategory = $this->groupBoards($boards);

        $body = $this->view->render('pages/community/admin/structure.php', [
            'pageTitle' => 'Admin · ' . $community->name,
            'community' => $community,
            'categories' => $categories,
            'boardsByCategory' => $boardsByCategory,
            'errors' => $errors,
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'activePath' => $request->path,
            'navSections' => [
                [
                    'title' => 'Admin',
                    'items' => [
                        ['label' => 'Structure', 'href' => '/c/' . $community->slug . '/admin/structure'],
                        ['label' => 'View community', 'href' => '/c/' . $community->slug],
                    ],
                ],
            ],
        ]);

        return new Response(
            status: $errors === [] ? 200 : 422,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    public function createCategory(Request $request): Response
    {
        $community = $this->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound();
        }

        $name = trim((string) ($request->body['name'] ?? ''));
        $position = (int) ($request->body['position'] ?? 0);

        if ($name === '') {
            return $this->structure($request, ['Category name is required.']);
        }

        $this->categories->create($community->id, $name, $position, time());

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function updateCategory(Request $request): Response
    {
        $community = $this->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound();
        }

        $categoryId = (int) ($request->params['category'] ?? 0);
        $category = $this->categories->findById($categoryId);
        if ($category === null || $category->communityId !== $community->id) {
            return $this->notFound();
        }

        $name = trim((string) ($request->body['name'] ?? ''));
        $position = (int) ($request->body['position'] ?? 0);

        if ($name === '') {
            return $this->structure($request, ['Category name is required.']);
        }

        $this->categories->update($category->id, $name, $position, time());

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function deleteCategory(Request $request): Response
    {
        $community = $this->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound();
        }

        $categoryId = (int) ($request->params['category'] ?? 0);
        $category = $this->categories->findById($categoryId);
        if ($category === null || $category->communityId !== $community->id) {
            return $this->notFound();
        }

        $this->categories->delete($category->id);

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function createBoard(Request $request): Response
    {
        $community = $this->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound();
        }

        $categoryId = (int) ($request->body['category_id'] ?? 0);
        $category = $this->categories->findById($categoryId);
        if ($category === null || $category->communityId !== $community->id) {
            return $this->structure($request, ['Invalid category selected.']);
        }

        $name = trim((string) ($request->body['name'] ?? ''));
        $description = trim((string) ($request->body['description'] ?? ''));
        $position = (int) ($request->body['position'] ?? 0);
        $isLocked = isset($request->body['is_locked']);

        if ($name === '') {
            return $this->structure($request, ['Board name is required.']);
        }

        $this->boards->create(
            communityId: $community->id,
            categoryId: $category->id,
            name: $name,
            description: $description,
            position: $position,
            isLocked: $isLocked,
            customCss: null,
            timestamp: time(),
        );

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function updateBoard(Request $request): Response
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

        $name = trim((string) ($request->body['name'] ?? ''));
        $description = trim((string) ($request->body['description'] ?? ''));
        $position = (int) ($request->body['position'] ?? 0);
        $isLocked = isset($request->body['is_locked']);

        if ($name === '') {
            return $this->structure($request, ['Board name is required.']);
        }

        $this->boards->update(
            id: $board->id,
            name: $name,
            description: $description,
            position: $position,
            isLocked: $isLocked,
            customCss: $board->customCss,
            timestamp: time(),
        );

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function deleteBoard(Request $request): Response
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

        $this->boards->delete($board->id);

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
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

        return $grouped;
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
