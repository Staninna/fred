<?php

declare(strict_types=1);

namespace Fred\Application\Moderation;

use Fred\Infrastructure\Database\BanRepository;
use RuntimeException;

final readonly class DeleteBanService
{
    public function __construct(private BanRepository $bans)
    {
    }

    public function delete(int $banId): void
    {
        if ($banId <= 0) {
            throw new RuntimeException('Invalid ban ID.');
        }

        $this->bans->delete($banId);
    }
}
