<?php

declare(strict_types=1);

namespace Fred\Application\Seed;

final class ProgressTracker
{
    /** @var list<string> */
    private array $entries = [];

    /**
     * @param callable(string):void|null $writer
     */
    public function __construct(private mixed $writer = null)
    {
    }

    public function log(string $message): void
    {
        $this->entries[] = $message;

        if ($this->writer !== null) {
            ($this->writer)($message);
        }
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->entries;
    }
}
