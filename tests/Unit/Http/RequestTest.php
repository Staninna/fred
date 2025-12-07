<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Fred\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function testWithParamsPreservesSession(): void
    {
        $request = new Request(
            method: 'GET',
            path: '/example',
            query: [],
            body: [],
            files: [],
            params: [],
            headers: ['X-Test' => '1'],
            session: ['user_id' => 123],
        );

        $updated = $request->withParams(['id' => 'abc']);

        self::assertSame(['user_id' => 123], $updated->session);
        self::assertSame(['id' => 'abc'], $updated->params);
        self::assertSame(['X-Test' => '1'], $updated->headers);
    }
}
