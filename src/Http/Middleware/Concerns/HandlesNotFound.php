<?php

declare(strict_types=1);

namespace Fred\Http\Middleware\Concerns;

use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\View\ViewRenderer;

/** Shared helper for middlewares that need to render 404 pages. */
trait HandlesNotFound
{
    abstract protected function view(): ViewRenderer;

    protected function config(): ?AppConfig
    {
        return null;
    }

    protected function notFound(Request $request, ?string $context = null): Response
    {
        return Response::notFound(
            view: $this->view(),
            config: $this->config(),
            request: $request,
            context: $context,
        );
    }
}
