<?php

declare(strict_types=1);

namespace Fred\Application\Auth;

final readonly class PermissionService
{
    public function canModerate(CurrentUser $user): bool
    {
        return \in_array($user->role, ['admin', 'moderator'], true);
    }

    public function canBan(CurrentUser $user): bool
    {
        return $user->role === 'admin';
    }

    public function canMoveThread(CurrentUser $user): bool
    {
        return $this->canModerate($user);
    }

    public function canEditAnyPost(CurrentUser $user): bool
    {
        return $this->canModerate($user);
    }
}
