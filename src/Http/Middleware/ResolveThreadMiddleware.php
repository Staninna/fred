<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Domain\Community\Community;
use Fred\Http\Controller\CommunityHelper;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ResolveThreadMiddleware
{
    public function __construct(
        private CommunityHelper $communityHelper,
        private ThreadRepository $threads,
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

        $threadId = (int) ($request->params['thread'] ?? 0);
        $thread = $this->threads->findById($threadId);
        if ($thread === null || $thread->communityId !== $community->id) {
            return Response::notFound(
                view: $this->view,
                request: $request,
            );
        }

        $board = $this->communityHelper->resolveBoard($community, (string) $thread->boardId);
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
                ->withAttribute('thread', $thread)
                ->withAttribute('board', $board)
                ->withAttribute('category', $category),
        );
    }
}
