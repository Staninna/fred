<?php

declare(strict_types=1);

namespace Fred\Domain\Auth;

final readonly class Profile
{
    public function __construct(
        public int $id,
        public int $userId,
        public int $communityId,
        public string $bio,
        public string $location,
        public string $website,
        public string $signatureRaw,
        public string $signatureParsed,
        public string $avatarPath,
        public int $createdAt,
        public int $updatedAt,
    ) {
    }
}
