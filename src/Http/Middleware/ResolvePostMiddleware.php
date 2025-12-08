<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Http\Middleware\Concerns\HandlesNotFound;
use Fred\Domain\Community\Community;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ResolvePostMiddleware
{
    use HandlesNotFound;

    public function __construct(
        private PostRepository $posts,
        private ThreadRepository $threads,
        private BoardRepository $boards,
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

        $postId = (int) ($request->params['post'] ?? 0);
        $post = $this->posts->findById($postId);
        if ($post === null || $post->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $thread = $this->threads->findById($post->threadId);
        if ($thread === null || $thread->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $board = $this->boards->findById($thread->boardId);
        if ($board === null || $board->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $category = $this->categories->findById($board->categoryId);
        if ($category === null || $category->communityId !== $community->id) {
            return $this->notFound($request);
        }

        return $next(
            $request
                ->withAttribute('post', $post)
                ->withAttribute('thread', $thread)
                ->withAttribute('board', $board)
                ->withAttribute('category', $category),
        );
    }

    protected function view(): ViewRenderer
    {
        return $this->view;
    }
}
