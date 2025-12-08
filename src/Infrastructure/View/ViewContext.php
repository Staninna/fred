<?php

declare(strict_types=1);

namespace Fred\Infrastructure\View;

use function array_key_exists;

use ArrayAccess;

/**
 * Fluent view data container that reduces verbosity when passing data to views.
 *
 * Usage:
 *   $ctx = ViewContext::make()
 *       ->set('pageTitle', 'My Page')
 *       ->set('user', $user)
 *       ->merge(['foo' => 'bar', 'baz' => 'qux']);
 *
 * @implements ArrayAccess<string, mixed>
 */
final class ViewContext implements ArrayAccess
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @param array<string, mixed> $initial */
    private function __construct(array $initial = [])
    {
        $this->data = $initial;
    }

    /** @param array<string, mixed> $initial */
    public static function make(array $initial = []): self
    {
        return new self($initial);
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function merge(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[(string) $offset]);
    }
}
