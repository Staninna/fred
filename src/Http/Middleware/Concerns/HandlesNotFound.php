<?php

declare(strict_types=1);

namespace Fred\Http\Middleware\Concerns;

use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\View\ViewRenderer;

/** Shared helper for middlewares that need to render 404 pages. */
trait HandlesNotFound
{
    abstract protected function view(): ViewRenderer;

    protected function notFound(Request $request): Response
    {
        return Response::notFound(
            view: $this->view(),
            request: $request,
        );
    }
}
