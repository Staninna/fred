<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use Fred\Application\Auth\CurrentUser;
use Fred\Infrastructure\Database\ReportRepository;
use RuntimeException;

use function strlen;
use function time;
use function trim;

final readonly class ReportPostService
{
    public function __construct(private ReportRepository $reports)
    {
    }

    public function report(
        CurrentUser $currentUser,
        int $communityId,
        int $postId,
        string $reason,
    ): void {
        $reason = trim($reason);

        if ($reason === '' || strlen($reason) > 500) {
            throw new RuntimeException('Reason must be between 1 and 500 characters.');
        }

        $this->reports->create(
            communityId: $communityId,
            postId: $postId,
            reporterId: $currentUser->id ?? 0,
            reason: $reason,
            timestamp: time(),
        );
    }
}
