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

    /**
     * Set a single value in the context.
     *
     * Common key types:
     * - 'pageTitle': string - Page title for <title> tag
     * - 'community': Community - Current community entity
     * - 'board': Board - Current board entity
     * - 'category': Category - Current category entity
     * - 'thread': Thread - Current thread entity
     * - 'posts': array<int, Post> - List of post entities
     * - 'errors': array<int, string> - Validation error messages
     * - 'old': array<string, string> - Previously submitted form values
     * - 'currentUser': CurrentUser|null - Authenticated user or guest
     * - 'usersById': array<int, User> - User entities indexed by ID
     * - 'profilesByUserId': array<int, Profile> - Profiles indexed by user ID
     * - 'attachmentsByPost': array<int, array<int, Attachment>> - Attachments grouped by post ID
     * - 'reactionsByPost': array<int, array<string, int>> - Reaction counts per post: [postId => [emoticon => count]]
     * - 'reactionUsersByPost': array<int, array<string, array{names: string[], extra: int}>> - Users per reaction per post
     * - 'userReactions': array<int, string|null> - Current user's reaction per post: [postId => emoticon]
     * - 'mentionsByPost': array<int, array<int, mixed>> - Mentions grouped by post ID
     * - 'linkPreviewsByPost': array<int, array<int, array{url:string, title:string, description:string|null, image:string|null, host:string}>> - Link previews per post
     * - 'linkPreviewUrlsByPost': array<int, string[]> - Extracted URLs per post for deferred preview loading
     * - 'emoticons': array<int, array{code: string, filename: string, url: string}> - Available emoticon list
     * - 'emoticonMap': array<string, string> - Emoticon code to URL mapping
     * - 'pagination': array{page: int, perPage: int, totalPages: int} - Pagination metadata
     * - 'navSections': array<int, mixed> - Navigation menu structure
     * - 'customCss': string - Community/board custom CSS
     */
    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Merge multiple values into the context at once.
     *
     * @param array<string, mixed> $data Associative array of key-value pairs to merge.
     *                                    Keys should follow the same conventions as set().
     */
    public function merge(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * Check if a key exists in the context.
     *
     * @param string $key The context key to check
     * @return bool True if key exists, false otherwise
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Retrieve a value from the context.
     *
     * @param string $key The context key (see set() for common keys)
     * @param mixed $default Default value if key does not exist
     * @return mixed The value associated with the key, or default
     */
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
