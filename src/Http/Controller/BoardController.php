<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class BoardController extends Controller
{
    public function __construct(
        ViewRenderer $view,
        AppConfig $config,
        AuthService $auth,
        CommunityContext $communityContext,
        private PermissionService $permissions,
        private ThreadRepository $threads,
    ) {
        parent::__construct($view, $config, $auth, $communityContext);
    }

    public function show(Request $request): Response
    {
        $ctxRequest = $request->context();
        /** @var Community|null $community */
        $community = $ctxRequest->community;
        /** @var Board|null $board */
        $board = $ctxRequest->board;
        /** @var Category|null $category */
        $category = $ctxRequest->category;

        if (!$community instanceof Community || !$board instanceof Board || !$category instanceof Category) {
            return $this->notFound($request, 'Required attributes missing in BoardController::show');
        }

        $page = (int) ($request->query['page'] ?? 1);
        $page = max($page, 1);
        $perPage = 20;
        $totalThreads = $this->threads->countByBoardId($board->id);
        $totalPages = $totalThreads === 0 ? 1 : (int) ceil($totalThreads / $perPage);

        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $structure = $this->communityContext->structureForCommunity($community);
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
            ->set('navSections', $this->communityContext->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
            ))
            ->set('customCss', trim(($community->customCss ?? '') . "\n" . ($board->customCss ?? '')));

        return Response::view($this->view, 'pages/board/show.php', $ctx);
    }
}
