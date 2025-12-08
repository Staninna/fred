<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class BoardController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private PermissionService $permissions,
        private CommunityHelper $communityHelper,
        private CategoryRepository $categories,
        private ThreadRepository $threads,
    ) {
    }

    public function show(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $boardSlug = (string) ($request->params['board'] ?? '');
        $board = $this->communityHelper->resolveBoard($community, $boardSlug);
        if ($board === null) {
            return $this->notFound($request);
        }

        $category = $this->categories->findById($board->categoryId);
        if ($category === null || $category->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $page = (int) ($request->query['page'] ?? 1);
        $page = $page < 1 ? 1 : $page;
        $perPage = 20;
        $totalThreads = $this->threads->countByBoardId($board->id);
        $totalPages = $totalThreads === 0 ? 1 : (int) ceil($totalThreads / $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $structure = $this->communityHelper->structureForCommunity($community);
        $threads = $this->threads->listByBoardIdPaginated($board->id, $perPage, $offset);
        $currentUser = $this->auth->currentUser();

        $ctx = ViewContext::make()
            ->set('pageTitle', $board->name)
            ->set('community', $community)
            ->set('board', $board)
            ->set('category', $category)
            ->set('threads', $threads)
            ->set('totalThreads', $totalThreads)
            ->set('pagination', [
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
            ])
            ->set('currentCommunity', $community)
            ->set('canModerate', $this->permissions->canModerate($currentUser, $community->id))
            ->set('canCreateThread', $this->permissions->canCreateThread($currentUser))
            ->set('navSections', $this->communityHelper->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
            ))
            ->set('customCss', trim(($community->customCss ?? '') . "\n" . ($board->customCss ?? '')));

        return Response::view($this->view, 'pages/board/show.php', $ctx);
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
