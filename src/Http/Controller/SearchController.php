<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Application\Search\SearchService;
use Fred\Domain\Community\Community;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;

use function trim;

final readonly class SearchController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private PermissionService $permissions,
        private CommunityContext $communityContext,
        private SearchService $search,
        private BoardRepository $boards,
        private CategoryRepository $categories,
        private UserRepository $users,
    ) {
    }

    public function search(Request $request): Response
    {
        $community = $request->attribute('community');
        if (!$community instanceof Community) {
            return $this->notFound($request);
        }

        $structure = $this->structureForCommunity($community);
        $query = trim((string) ($request->query['q'] ?? ''));
        $boardParam = (string) ($request->query['board'] ?? '');
        $userParam = trim((string) ($request->query['user'] ?? ''));
        $usernames = $this->users->listUsernames();

        $boardFilter = $boardParam !== '' ? $this->communityContext->resolveBoard($community, $boardParam) : null;
        $userFilter = $userParam !== '' ? $this->users->findByUsername($userParam) : null;

        $errors = [];
        if ($query === '') {
            $errors[] = 'Enter a search query.';
        }

        if ($boardParam !== '' && $boardFilter === null) {
            $errors[] = 'Board not found.';
        }

        if ($userParam !== '' && $userFilter === null) {
            $errors[] = 'User not found.';
        }

        $threads = [];
        $posts = [];

        if ($errors === [] && $query !== '') {
            $threads = $this->search->searchThreads(
                communityId: $community->id,
                boardId: $boardFilter?->id,
                userId: $userFilter?->id,
                query: $query,
                limit: 10,
                offset: 0,
            );

            $posts = $this->search->searchPosts(
                communityId: $community->id,
                boardId: $boardFilter?->id,
                userId: $userFilter?->id,
                query: $query,
                limit: 10,
                offset: 0,
            );

            if ($threads === [] && $posts === []) {
                $errors[] = 'No results found.';
            }
        }

        $currentUser = $this->auth->currentUser();

        $ctx = ViewContext::make()
            ->set('pageTitle', 'Search')
            ->set('community', $community)
            ->set('query', $query)
            ->set('boardFilter', $boardFilter)
            ->set('userFilter', $userFilter)
            ->set('threads', $threads)
            ->set('posts', $posts)
            ->set('boards', $structure['boards'])
            ->set('errors', $errors)
            ->set('currentCommunity', $community)
            ->set('canModerate', $this->permissions->canModerate($currentUser, $community->id))
            ->set('usernames', $usernames)
            ->set('navSections', $this->communityContext->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
            ))
            ->set('customCss', trim((string) ($community->customCss ?? '')));

        return Response::view(
            $this->view,
            'pages/search/results.php',
            $ctx,
            status: $errors === [] ? 200 : 422,
        );
    }

    private function notFound(Request $request): Response
    {
        return Response::notFound(
            view: $this->view,
            request: $request,
        );
    }

    private function structureForCommunity(Community $community): array
    {
        $categories = $this->categories->listByCommunityId($community->id);
        $boards = $this->boards->listByCommunityId($community->id);

        return [
            'categories' => $categories,
            'boards' => $boards,
            'boardsByCategory' => $this->groupBoards($boards),
        ];
    }

    /** @param \Fred\Domain\Community\Board[] $boards @return array<int, \Fred\Domain\Community\Board[]> */
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
}
