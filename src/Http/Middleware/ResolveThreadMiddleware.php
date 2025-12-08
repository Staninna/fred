<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Http\Middleware\Concerns\HandlesNotFound;
use Fred\Domain\Community\Community;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ResolveThreadMiddleware
{
    use HandlesNotFound;

    public function __construct(
        private BoardRepository $boards,
        private ThreadRepository $threads,
        private CategoryRepository $categories,
        private ViewRenderer $view,
        private AppConfig $config,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $community = $request->attribute('community');
        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in ResolveThreadMiddleware');
        }

        $threadId = (int) ($request->params['thread'] ?? 0);
        $thread = $this->threads->findById($threadId);
        if ($thread === null || $thread->communityId !== $community->id) {
            return $this->notFound($request, 'Thread not found: ' . $threadId);
        }

        $board = $this->boards->findById($thread->boardId);
        if ($board === null || $board->communityId !== $community->id) {
            return $this->notFound($request, 'Board mismatch for thread: ' . $thread->title);
        }

        $category = $this->categories->findById($board->categoryId);
        if ($category === null || $category->communityId !== $community->id) {
            return $this->notFound($request, 'Category mismatch for board: ' . $board->name);
        }

        return $next(
            $request
                ->withAttribute('thread', $thread)
                ->withAttribute('board', $board)
                ->withAttribute('category', $category),
        );
    }

    protected function view(): ViewRenderer
    {
        return $this->view;
    }

    protected function config(): ?AppConfig
    {
        return $this->config;
    }
}
