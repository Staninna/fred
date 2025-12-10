<?php

declare(strict_types=1);

namespace Fred\Http\Navigation;

use function array_slice;
use function count;

use Fred\Http\Request;
use Fred\Http\Response;

use function http_build_query;
use function is_array;
use function is_int;
use function preg_match;
use function str_starts_with;
use function strtoupper;

final class NavigationTracker
{
    public function __construct(private readonly int $maxEntries = 20)
    {
    }

    /** @param array<string, mixed> $session */
    public function track(Request $request, array &$session): ?Response
    {
        $method = strtoupper($request->method);
        $history = $this->sanitizeHistory($session['nav_history'] ?? []);
        $index = $this->sanitizeIndex($session['nav_index'] ?? null, $history);

        if ($request->path === '/nav/back') {
            if ($index > 0) {
                $index--;
            }

            $session['nav_index'] = $index;
            $session['nav_skip_slice'] = true;
            $target = $history[$index] ?? '/';

            return Response::redirect($target);
        }

        if ($request->path === '/nav/forward') {
            if ($index < count($history) - 1) {
                $index++;
            }

            $session['nav_index'] = $index;
            $session['nav_skip_slice'] = true;
            $target = $history[$index] ?? '/';

            return Response::redirect($target);
        }

        if ($method !== 'GET' || !$this->isTrackablePath($request->path)) {
            return null;
        }

        $skipSlice = (bool) ($session['nav_skip_slice'] ?? false);
        $session['nav_skip_slice'] = false;

        if (!$skipSlice && $index !== count($history) - 1) {
            $history = array_slice($history, 0, $index + 1);
            $index = count($history) - 1;
        }

        $fullPath = $request->path;

        if ($request->query !== []) {
            $fullPath .= '?' . http_build_query($request->query);
        }

        if ($index >= 0 && isset($history[$index]) && $history[$index] === $fullPath) {
            // already at this position; keep index as-is
        } elseif ($history === [] || $history[count($history) - 1] !== $fullPath) {
            $history[] = $fullPath;

            if (count($history) > $this->maxEntries) {
                $history = array_slice($history, -$this->maxEntries);
            }
            $index = count($history) - 1;
        }

        $session['nav_history'] = $history;
        $session['nav_index'] = $index;

        return null;
    }

    private function isTrackablePath(string $path): bool
    {
        if ($path === '/nav/back' || $path === '/nav/forward') {
            return false;
        }

        if (preg_match('#\\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|txt)$#i', $path)) {
            return false;
        }

        if (str_starts_with($path, '/uploads/')) {
            return false;
        }

        return true;
    }

    /** @return string[] */
    private function sanitizeHistory(mixed $history): array
    {
        return is_array($history) ? $history : [];
    }

    /** @param string[] $history */
    private function sanitizeIndex(mixed $index, array $history): int
    {
        if (!is_int($index)) {
            return $history !== [] ? count($history) - 1 : -1;
        }

        if ($history === []) {
            return -1;
        }

        $max = count($history) - 1;

        if ($index < 0) {
            return 0;
        }

        if ($index > $max) {
            return $max;
        }

        return $index;
    }
}
