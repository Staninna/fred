<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Http\Middleware\Concerns\HandlesNotFound;
use Fred\Domain\Community\Community;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ResolveBoardMiddleware
{
    use HandlesNotFound;

    public function __construct(
        private CommunityContext $communityContext,
        private CategoryRepository $categories,
        private ViewRenderer $view,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $community = $request->attribute('community');
        if (!$community instanceof Community) {
            return $this->notFound($request);
        }

        $boardSlug = (string) ($request->params['board'] ?? '');
        $board = $this->communityContext->resolveBoard($community, $boardSlug);
        if ($board === null) {
            return $this->notFound($request);
        }

        $category = $this->categories->findById($board->categoryId);
        if ($category === null || $category->communityId !== $community->id) {
            return $this->notFound($request);
        }

        return $next(
            $request
                ->withAttribute('board', $board)
                ->withAttribute('category', $category),
        );
    }

    protected function view(): ViewRenderer
    {
        return $this->view;
    }
}
