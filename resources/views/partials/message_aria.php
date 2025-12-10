<?php

declare(strict_types=1);
/**
 * Helper partial for building aria-describedby and message targets.
 * Sets $messageAria variable for use in form inputs.
 *
 * @var string $messageIdPrefix The prefix for error/success IDs (e.g. 'login-form')
 * @var array<int, string> $errors Error messages (if any)
 * @var string|null $success Success message (if any)
 */

use Fred\Infrastructure\View\ViewHelper;

$messageAria = ViewHelper::buildMessageAria(
    !empty($errors),
    !empty($success ?? ''),
    $messageIdPrefix
);
