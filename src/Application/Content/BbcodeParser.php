<?php

declare(strict_types=1);

namespace Fred\Application\Content;

final class BbcodeParser
{
    public function parse(string $input): string
    {
        $escaped = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        $escaped = $this->convertLineQuotes($escaped);

        $replacements = [
            'b' => ['<strong>', '</strong>'],
            'i' => ['<em>', '</em>'],
            'code' => ['<pre><code>', '</code></pre>'],
            'quote' => ['<blockquote>', '</blockquote>'],
        ];

        foreach ($replacements as $tag => [$open, $close]) {
            $pattern = \sprintf('#\[%s](.*?)\[/\s*%s]#si', $tag, $tag);
            $escaped = preg_replace($pattern, $open . '$1' . $close, $escaped) ?? $escaped;
        }

        $escaped = $this->parseUrlTags($escaped);
        return nl2br($escaped);
    }

    private function parseUrlTags(string $input): string
    {
        $pattern = '#\[url](https?://[^\s\[\]]+)\[/url]#i';
        $input = preg_replace($pattern, '<a href="$1" rel="noopener" target="_blank">$1</a>', $input) ?? $input;

        $patternWithLabel = '#\[url=(https?://[^\s\[\]]+)](.*?)\[/url]#i';

        return preg_replace($patternWithLabel, '<a href="$1" rel="noopener" target="_blank">$2</a>', $input) ?? $input;
    }

    private function convertLineQuotes(string $input): string
    {
        return preg_replace_callback('/&gt;&gt;(\d+)/', static function (array $matches): string {
            $id = $matches[1];

            return \sprintf('<a class="quote-link" href="#post-%s">&gt;&gt;%s</a>', $id, $id);
        }, $input) ?? $input;
    }
}
