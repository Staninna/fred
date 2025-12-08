<?php

declare(strict_types=1);

namespace Fred\Application\Security;

use Fred\Http\Request;

use function bin2hex;
use function hash_equals;
use function random_bytes;

final class CsrfGuard
{
    private const string SESSION_KEY = '_csrf_token';

    public function token(): string
    {
        $stored = $_SESSION[self::SESSION_KEY] ?? null;

        if (!\is_string($stored) || $stored === '') {
            $stored = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $stored;
        }

        return $stored;
    }

    public function isValid(Request $request): bool
    {
        $provided = $this->extractToken($request);
        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        if (!\is_string($provided) || !\is_string($expected)) {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    private function extractToken(Request $request): ?string
    {
        $fromBody = $request->body['_token'] ?? null;
        if (\is_string($fromBody) && $fromBody !== '') {
            return $fromBody;
        }

        $fromHeader = $request->header('X-CSRF-TOKEN');
        if (\is_string($fromHeader) && $fromHeader !== '') {
            return $fromHeader;
        }

        return null;
    }
}
