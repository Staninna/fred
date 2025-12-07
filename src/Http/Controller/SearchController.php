<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Application\Search\SearchService;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\View\ViewRenderer;

use function trim;

final readonly class SearchController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private PermissionService $permissions,
        private CommunityHelper $communityHelper,
        private SearchService $search,
        private UserRepository $users,
    ) {
    }

    public function search(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $structure = $this->communityHelper->structureForCommunity($community);
        $query = trim((string) ($request->query['q'] ?? ''));
        $boardParam = (string) ($request->query['board'] ?? '');
        $userParam = trim((string) ($request->query['user'] ?? ''));
        $usernames = $this->users->listUsernames();

        $boardFilter = $boardParam !== '' ? $this->communityHelper->resolveBoard($community, $boardParam) : null;
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

        $body = $this->view->render('pages/search/results.php', [
            'pageTitle' => 'Search',
            'community' => $community,
            'query' => $query,
            'boardFilter' => $boardFilter,
            'userFilter' => $userFilter,
            'threads' => $threads,
            'posts' => $posts,
            'boards' => $structure['boards'],
            'errors' => $errors,
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'canModerate' => $this->permissions->canModerate($this->auth->currentUser(), $community->id),
            'activePath' => $request->path,
            'usernames' => $usernames,
            'navSections' => $this->communityHelper->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
            ),
        ]);

        return new Response(
            status: $errors === [] ? 200 : 422,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    private function notFound(Request $request): Response
    {
        return Response::notFound(
            view: $this->view,
            config: $this->config,
            auth: $this->auth,
            request: $request,
            navSections: $this->communityHelper->navForCommunity(),
        );
    }
}
