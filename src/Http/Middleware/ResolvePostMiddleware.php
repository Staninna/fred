<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Domain\Community\Community;
use Fred\Http\Middleware\Concerns\HandlesNotFound;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
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
        private AppConfig $config,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in ResolvePostMiddleware');
        }

        $postId = (int) ($request->params['post'] ?? 0);
        $post = $this->posts->findById($postId);

        if ($post === null || $post->communityId !== $community->id) {
            return $this->notFound($request, 'Post not found: ' . $postId);
        }

        $thread = $this->threads->findById($post->threadId);

        if ($thread === null || $thread->communityId !== $community->id) {
            return $this->notFound($request, 'Thread mismatch for post: ' . $postId);
        }

        $board = $this->boards->findById($thread->boardId);

        if ($board === null || $board->communityId !== $community->id) {
            return $this->notFound($request, 'Board mismatch for thread: ' . $thread->title);
        }

        $category = $this->categories->findById($board->categoryId);

        if ($category === null || $category->communityId !== $community->id) {
            return $this->notFound($request, 'Category mismatch for board: ' . $board->name);
        }

        $context = $request->context()
            ->withPost($post)
            ->withThread($thread)
            ->withBoard($board)
            ->withCategory($category);

        return $next(
            $request
                ->withContext($context)
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

    protected function config(): ?AppConfig
    {
        return $this->config;
    }
}
