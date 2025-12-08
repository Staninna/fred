<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Domain\Community\Community;
use Fred\Http\Controller\CommunityHelper;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ResolveBoardMiddleware
{
    public function __construct(
        private CommunityHelper $communityHelper,
        private CategoryRepository $categories,
        private ViewRenderer $view,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $community = $request->attribute('community');
        if (!$community instanceof Community) {
            return Response::notFound(
                view: $this->view,
                request: $request,
            );
        }

        $boardSlug = (string) ($request->params['board'] ?? '');
        $board = $this->communityHelper->resolveBoard($community, $boardSlug);
        if ($board === null) {
            return Response::notFound(
                view: $this->view,
                request: $request,
            );
        }

        $category = $this->categories->findById($board->categoryId);
        if ($category === null || $category->communityId !== $community->id) {
            return Response::notFound(
                view: $this->view,
                request: $request,
            );
        }

        return $next(
            $request
                ->withAttribute('board', $board)
                ->withAttribute('category', $category),
        );
    }
}
