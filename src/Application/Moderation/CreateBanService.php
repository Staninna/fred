<?php

declare(strict_types=1);

namespace Fred\Application\Moderation;

use Fred\Infrastructure\Database\BanRepository;
use Fred\Infrastructure\Database\UserRepository;
use RuntimeException;

use function time;
use function trim;

final readonly class CreateBanService
{
    public function __construct(
        private UserRepository $users,
        private BanRepository $bans,
    ) {
    }

    public function create(
        string $username,
        string $reason,
        ?string $expiresAtString = null,
    ): void {
        $username = trim($username);
        $reason = trim($reason);

        if ($username === '') {
            throw new RuntimeException('Username is required.');
        }

        if ($reason === '') {
            throw new RuntimeException('Reason is required.');
        }

        $user = $this->users->findByUsername($username);

        if ($user === null) {
            throw new RuntimeException('User not found.');
        }

        $expiresAt = null;

        if ($expiresAtString !== null) {
            $expiresAtString = trim($expiresAtString);

            if ($expiresAtString !== '') {
                $expiresAt = strtotime($expiresAtString) ?: null;
            }
        }

        $this->bans->create(
            userId: $user->id,
            reason: $reason,
            expiresAt: $expiresAt,
            timestamp: time(),
        );
    }
}
