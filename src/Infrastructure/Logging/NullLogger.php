<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

final class NullLogger extends AbstractLogger
{
    public function log($level, $message, array $context = []): void
    {
        // no-op
    }
}
