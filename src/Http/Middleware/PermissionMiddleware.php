<?php

declare(strict_types=1);

namespace Fred\Http\Middleware;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Domain\Community\Community;
use Fred\Http\Request;
use Fred\Http\Response;

final readonly class PermissionMiddleware
{
    public function __construct(
        private AuthService $auth,
        private PermissionService $permissions,
    ) {
    }

    public function check(string $permission): callable
    {
        return function (Request $request, callable $next) use ($permission): Response {
            $currentUser = $this->auth->currentUser();
            /** @var Community|null $community */
            $community = $request->attribute('community');
            $communityId = $community?->id;

            $allowed = match ($permission) {
                'canModerate' => $this->permissions->canModerate($currentUser, $communityId),
                'canCreateCommunity' => $this->permissions->canCreateCommunity($currentUser),
                'canReply' => $this->permissions->canReply($currentUser),
                'canEditAnyPost' => $this->permissions->canEditAnyPost($currentUser, $communityId),
                'canDeleteAnyPost' => $this->permissions->canDeleteAnyPost($currentUser, $communityId),
                'canBan' => $this->permissions->canBan($currentUser, $communityId),
                'canLockThread' => $this->permissions->canLockThread($currentUser, $communityId),
                'canStickyThread' => $this->permissions->canStickyThread($currentUser, $communityId),
                'canMoveThread' => $this->permissions->canMoveThread($currentUser, $communityId),
                default => false,
            };

            if (!$allowed) {
                return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
            }

            return $next($request);
        };
    }
}
