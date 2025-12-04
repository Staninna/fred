<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Logging;

use Psr\Log\AbstractLogger;

final class FileLogger extends AbstractLogger
{
    public function __construct(private readonly string $path)
    {
    }

    public function log($level, $message, array $context = []): void
    {
        $line = \sprintf(
            "[%s] %s: %s %s\n",
            date(DATE_ATOM),
            strtoupper((string) $level),
            (string) $message,
            $context === [] ? '' : json_encode($context),
        );

        file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}
