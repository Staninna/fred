<?php

declare(strict_types=1);

namespace Fred\Domain\Auth;

/**
 * Common role slugs used across the application.
 */
final class RoleSlug
{
    public const string GUEST = 'guest';
    public const string MEMBER = 'member';
    public const string MODERATOR = 'moderator';
    public const string ADMIN = 'admin';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::GUEST,
            self::MEMBER,
            self::MODERATOR,
            self::ADMIN,
        ];
    }
}
