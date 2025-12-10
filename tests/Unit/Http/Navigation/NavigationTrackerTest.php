<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Navigation;

use Fred\Http\Navigation\NavigationTracker;
use Fred\Http\Request;
use Fred\Http\Response;
use PHPUnit\Framework\TestCase;

final class NavigationTrackerTest extends TestCase
{
    private function tracker(int $maxEntries = 20): NavigationTracker
    {
        return new NavigationTracker($maxEntries);
    }

    /**
     * @param array<string, string|int> $query
     */
    private function request(string $path, string $method = 'GET', array $query = []): Request
    {
        return new Request(
            method: $method,
            path: $path,
            query: $query,
            body: [],
            files: [],
            params: [],
            headers: [],
            session: [],
        );
    }

    public function testBackMovesOneStepAndSetsSkipSlice(): void
    {
        $session = [
            'nav_history' => ['/a', '/b', '/c'],
            'nav_index' => 2,
        ];

        $response = $this->tracker()->track($this->request('/nav/back'), $session);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('/b', $response->headers['Location'] ?? null);
        self::assertSame(1, $session['nav_index']);
        self::assertTrue($session['nav_skip_slice']);
    }

    public function testBackAtStartDoesNotUnderflow(): void
    {
        $session = [
            'nav_history' => ['/home'],
            'nav_index' => 0,
        ];

        $response = $this->tracker()->track($this->request('/nav/back'), $session);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('/home', $response->headers['Location'] ?? null);
        self::assertSame(0, $session['nav_index']);
    }

    public function testForwardAdvancesWhenPossible(): void
    {
        $session = [
            'nav_history' => ['/a', '/b', '/c'],
            'nav_index' => 1,
        ];

        $response = $this->tracker()->track($this->request('/nav/forward'), $session);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('/c', $response->headers['Location'] ?? null);
        self::assertSame(2, $session['nav_index']);
        self::assertTrue($session['nav_skip_slice']);
    }

    public function testForwardAtEndDoesNotOverflow(): void
    {
        $session = [
            'nav_history' => ['/a', '/b'],
            'nav_index' => 1,
        ];

        $response = $this->tracker()->track($this->request('/nav/forward'), $session);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('/b', $response->headers['Location'] ?? null);
        self::assertSame(1, $session['nav_index']);
    }

    public function testNewPathTruncatesForwardHistory(): void
    {
        $session = [
            'nav_history' => ['/a', '/b', '/c'],
            'nav_index' => 1,
        ];

        $response = $this->tracker()->track($this->request('/d'), $session);

        self::assertNull($response);
        self::assertSame(['/a', '/b', '/d'], $session['nav_history']);
        self::assertSame(2, $session['nav_index']);
        self::assertFalse($session['nav_skip_slice']);
    }

    public function testSkipSlicePreservesForwardHistory(): void
    {
        $session = [
            'nav_history' => ['/a', '/b', '/c'],
            'nav_index' => 1,
            'nav_skip_slice' => true,
        ];

        $response = $this->tracker()->track($this->request('/d'), $session);

        self::assertNull($response);
        self::assertSame(['/a', '/b', '/c', '/d'], $session['nav_history']);
        self::assertSame(3, $session['nav_index']);
        self::assertFalse($session['nav_skip_slice']);
    }

    public function testDoesNotDuplicateCurrentEntry(): void
    {
        $session = [
            'nav_history' => ['/thread/1'],
            'nav_index' => 0,
        ];

        $response = $this->tracker()->track($this->request('/thread/1'), $session);

        self::assertNull($response);
        self::assertSame(['/thread/1'], $session['nav_history']);
        self::assertSame(0, $session['nav_index']);
    }

    public function testAppendsQueryStringToHistory(): void
    {
        $session = [];

        $response = $this->tracker()->track($this->request('/search', 'GET', ['q' => 'abc', 'page' => 2]), $session);

        self::assertNull($response);
        self::assertSame(['/search?q=abc&page=2'], $session['nav_history']);
        self::assertSame(0, $session['nav_index']);
    }

    public function testCapsHistoryLength(): void
    {
        $tracker = $this->tracker(3);
        $session = [];

        foreach (['/one', '/two', '/three', '/four', '/five'] as $path) {
            $tracker->track($this->request($path), $session);
        }

        self::assertSame(['/three', '/four', '/five'], $session['nav_history']);
        self::assertSame(2, $session['nav_index']);
    }

    public function testNonGetRequestsAreNotTracked(): void
    {
        $session = [
            'nav_history' => ['/start'],
            'nav_index' => 0,
        ];

        $response = $this->tracker()->track($this->request('/submit', 'POST'), $session);

        self::assertNull($response);
        self::assertSame(['/start'], $session['nav_history']);
        self::assertSame(0, $session['nav_index']);
        self::assertArrayNotHasKey('nav_skip_slice', $session);
    }

    public function testStaticAssetsAreNotTracked(): void
    {
        $session = [];

        $response = $this->tracker()->track($this->request('/style.css'), $session);

        self::assertNull($response);
        self::assertSame([], $session);
    }

    public function testUploadsAreNotTracked(): void
    {
        $session = [];

        $response = $this->tracker()->track($this->request('/uploads/2024/01/file.png'), $session);

        self::assertNull($response);
        self::assertSame([], $session);
    }

    public function testCorruptedSessionValuesAreSanitized(): void
    {
        $session = [
            'nav_history' => 'not-an-array',
            'nav_index' => 'not-an-int',
        ];

        $response = $this->tracker()->track($this->request('/home'), $session);

        self::assertNull($response);
        self::assertSame(['/home'], $session['nav_history']);
        self::assertSame(0, $session['nav_index']);
    }
}
