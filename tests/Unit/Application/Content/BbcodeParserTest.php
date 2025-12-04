<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Content;

use Fred\Application\Content\BbcodeParser;
use Tests\TestCase;

final class BbcodeParserTest extends TestCase
{
    public function testParsesBasicTags(): void
    {
        $parser = new BbcodeParser();
        $input = "[b]bold[/b] [i]italics[/i] [quote]quoted[/quote] [code]echo 'hi';[/code]";
        $output = $parser->parse($input);

        $this->assertStringContainsString('<strong>bold</strong>', $output);
        $this->assertStringContainsString('<em>italics</em>', $output);
        $this->assertStringContainsString('<blockquote>quoted</blockquote>', $output);
        $this->assertStringContainsString('<pre><code>echo &#039;hi&#039;;</code></pre>', $output);
    }

    public function testParsesUrls(): void
    {
        $parser = new BbcodeParser();
        $input = '[url]https://example.com[/url] and [url=https://example.com]site[/url]';
        $output = $parser->parse($input);

        $this->assertStringContainsString('<a href="https://example.com"', $output);
        $this->assertStringContainsString('site</a>', $output);
    }

    public function testConvertsQuoteLinks(): void
    {
        $parser = new BbcodeParser();
        $input = ">>123\nhello";
        $output = $parser->parse($input);

        $this->assertStringContainsString('href="#post-123"', $output);
        $this->assertStringContainsString('&gt;&gt;123', $output);
    }

    public function testEscapesHtml(): void
    {
        $parser = new BbcodeParser();
        $input = '<script>alert(1)</script>[b]bold[/b]';
        $output = $parser->parse($input);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $output);
        $this->assertStringContainsString('<strong>bold</strong>', $output);
    }
}
