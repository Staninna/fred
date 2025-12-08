<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Post;
use Fred\Domain\Forum\Thread;
use Fred\Http\Middleware\Concerns\HandlesNotFound;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewRenderer;

final readonly class ValidateResourceAttributesMiddleware
{
    use HandlesNotFound;

    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        // Check community
        if (isset($request->params['community'])) {
            $community = $request->attribute('community');
            if (!$community instanceof Community) {
                return $this->notFound($request, 'Community attribute missing or invalid');
            }
        }

        // Check board
        if (isset($request->params['board'])) {
            $board = $request->attribute('board');
            if (!$board instanceof Board) {
                return $this->notFound($request, 'Board attribute missing or invalid');
            }
        }

        // Check thread
        if (isset($request->params['thread'])) {
            $thread = $request->attribute('thread');
            if (!$thread instanceof Thread) {
                return $this->notFound($request, 'Thread attribute missing or invalid');
            }
        }

        // Check post
        if (isset($request->params['post'])) {
            $post = $request->attribute('post');
            if (!$post instanceof Post) {
                return $this->notFound($request, 'Post attribute missing or invalid');
            }
        }

        return $next($request);
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
