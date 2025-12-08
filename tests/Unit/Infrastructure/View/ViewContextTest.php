<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\View;

use Fred\Infrastructure\View\ViewContext;
use PHPUnit\Framework\TestCase;

final class ViewContextTest extends TestCase
{
    public function testMakeCreatesEmptyContext(): void
    {
        $ctx = ViewContext::make();
        $this->assertSame([], $ctx->all());
    }

    public function testMakeAcceptsInitialData(): void
    {
        $ctx = ViewContext::make(['foo' => 'bar']);
        $this->assertSame(['foo' => 'bar'], $ctx->all());
    }

    public function testSetAddsValue(): void
    {
        $ctx = ViewContext::make()->set('key', 'value');
        $this->assertSame('value', $ctx->get('key'));
    }

    public function testSetReturnsInstanceForChaining(): void
    {
        $ctx = ViewContext::make();
        $result = $ctx->set('a', 1);
        $this->assertSame($ctx, $result);
    }

    public function testMergeAddsMultipleValues(): void
    {
        $ctx = ViewContext::make(['a' => 1])
            ->merge(['b' => 2, 'c' => 3]);

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $ctx->all());
    }

    public function testMergeOverwritesExistingKeys(): void
    {
        $ctx = ViewContext::make(['a' => 1])
            ->merge(['a' => 2]);

        $this->assertSame(['a' => 2], $ctx->all());
    }

    public function testHasChecksKeyExistence(): void
    {
        $ctx = ViewContext::make(['key' => 'value']);
        $this->assertTrue($ctx->has('key'));
        $this->assertFalse($ctx->has('missing'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $ctx = ViewContext::make();
        $this->assertNull($ctx->get('missing'));
        $this->assertSame('default', $ctx->get('missing', 'default'));
    }

    public function testArrayAccessWorks(): void
    {
        $ctx = ViewContext::make();
        $ctx['foo'] = 'bar';

        $this->assertTrue(isset($ctx['foo']));
        $this->assertSame('bar', $ctx['foo']);

        unset($ctx['foo']);
        $this->assertFalse(isset($ctx['foo']));
    }

    public function testFluentChaining(): void
    {
        $ctx = ViewContext::make()
            ->set('a', 1)
            ->set('b', 2)
            ->merge(['c' => 3, 'd' => 4])
            ->set('e', 5);

        $this->assertSame([
            'a' => 1,
            'b' => 2,
            'c' => 3,
            'd' => 4,
            'e' => 5,
        ], $ctx->all());
    }
}
