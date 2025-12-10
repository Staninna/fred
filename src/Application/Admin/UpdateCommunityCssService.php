<?php

declare(strict_types=1);

namespace Fred\Application\Admin;

use Fred\Domain\Community\Community;
use Fred\Infrastructure\Database\CommunityRepository;
use RuntimeException;

use function strlen;
use function time;
use function trim;

final readonly class UpdateCommunityCssService
{
    public function __construct(private CommunityRepository $communities)
    {
    }

    public function update(Community $community, string $css): void
    {
        $css = trim($css);

        if (strlen($css) > 8000) {
            throw new RuntimeException('Community CSS is too long (max 8000 characters).');
        }

        $this->communities->update(
            id: $community->id,
            name: $community->name,
            description: $community->description,
            customCss: $css !== '' ? $css : null,
            timestamp: time(),
        );
    }
}
