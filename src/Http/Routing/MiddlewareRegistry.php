<?php

declare(strict_types=1);

namespace Fred\Http\Routing;

use RuntimeException;

final class MiddlewareRegistry
{
    /** @var array<string, callable> */
    private array $registry = [];

    public function register(string $alias, callable $middleware): void
    {
        $this->registry[$alias] = $middleware;
    }

    public function resolve(string $alias): callable
    {
        if (!isset($this->registry[$alias])) {
            throw new RuntimeException('Middleware alias not registered: ' . $alias);
        }

        return $this->registry[$alias];
    }

    /**
     * @param array<int, string> $aliases
     * @return array<int, callable>
     */
    public function resolveMany(array $aliases): array
    {
        return array_map(fn (string $alias) => $this->resolve($alias), $aliases);
    }

    public function resolver(): callable
    {
        return fn (string $alias): callable => $this->resolve($alias);
    }
}
