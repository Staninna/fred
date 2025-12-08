<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use function sprintf;

final class BbcodeParser
{
    public function parse(string $input, ?string $communitySlug = null): string
    {
        $escaped = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        $escaped = $this->convertLineQuotes($escaped);

        // Inline/simple tags
        $escaped = $this->replaceTag($escaped, 'b', '<strong class="bb-bold">', '</strong>');
        $escaped = $this->replaceTag($escaped, 'i', '<em class="bb-italic">', '</em>');
        $escaped = $this->replaceTag($escaped, 'u', '<u class="bb-underline">', '</u>');
        $escaped = $this->replaceTag($escaped, 's', '<s class="bb-strike">', '</s>');
        $escaped = $this->replaceTag($escaped, 'quote', '<blockquote class="bb-quote">', '</blockquote>');
        $escaped = $this->replaceTag($escaped, 'spoiler', '<span class="bb-spoiler">', '</span>');

        // Code and lists before URL/img to avoid mangling inside blocks
        $escaped = $this->replaceTag($escaped, 'code', '<pre class="bb-code"><code>', '</code></pre>');
        $escaped = $this->parseLists($escaped);

        // Media / links
        $escaped = $this->parseImages($escaped);
        $escaped = $this->parseUrlTags($escaped);

        if ($communitySlug !== null) {
            $escaped = $this->parseMentions($escaped, $communitySlug);
        }

        return nl2br($escaped);
    }

    private function replaceTag(string $input, string $tag, string $open, string $close): string
    {
        $pattern = sprintf('#\[%s](.*?)\[/\s*%s]#si', $tag, $tag);

        return preg_replace($pattern, $open . '$1' . $close, $input) ?? $input;
    }

    private function parseUrlTags(string $input): string
    {
        $pattern = '#\[url](https?://[^\s\[\]]+)\[/url]#i';
        $input = preg_replace_callback($pattern, fn (array $m) => $this->anchor($m[1], $m[1]), $input) ?? $input;

        $patternWithLabel = '#\[url=(https?://[^\s\[\]]+)](.*?)\[/url]#i';

        return preg_replace_callback($patternWithLabel, fn (array $m) => $this->anchor($m[1], $m[2]), $input) ?? $input;
    }

    private function parseImages(string $input): string
    {
        $pattern = '#\[img](https?://[^\s\[\]]+)\[/img]#i';

        return preg_replace_callback($pattern, function (array $m): string {
            $url = $this->sanitizeUrl($m[1]);

            if ($url === null) {
                return $m[0];
            }

            return '<figure class="bb-image"><img src="' . $url . '" alt="image" loading="lazy"></figure>';
        }, $input) ?? $input;
    }

    private function parseLists(string $input): string
    {
        $input = preg_replace('#\[list]\s*#i', '<ul>', $input) ?? $input;
        $input = preg_replace('#\[/list]#i', '</ul>', $input) ?? $input;

        return preg_replace('#\[\*]\s*([^\n\r\[]+)#', '<li>$1</li>', $input) ?? $input;
    }

    private function convertLineQuotes(string $input): string
    {
        return preg_replace_callback('/&gt;&gt;(\d+)/', static function (array $matches): string {
            $id = $matches[1];

            return sprintf('<a class="quote-link" href="#post-%s">&gt;&gt;%s</a>', $id, $id);
        }, $input) ?? $input;
    }

    private function parseMentions(string $input, string $communitySlug): string
    {
        return preg_replace_callback(
            '/(?<=^|[\s(&lt;\[])\@([A-Za-z0-9_.-]{3,32})(?=[.,;:!?\s&lt;&gt;\])]|$)/',
            static function (array $matches) use ($communitySlug): string {
                $username = $matches[1];
                $url = '/c/' . htmlspecialchars($communitySlug, ENT_QUOTES, 'UTF-8')
                     . '/u/' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

                return '<a href="' . $url . '">@' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</a>';
            },
            $input
        ) ?? $input;
    }

    private function anchor(string $href, string $label): string
    {
        $safe = $this->sanitizeUrl($href);

        if ($safe === null) {
            return $label;
        }

        return '<a href="' . $safe . '" rel="noopener" target="_blank">' . $label . '</a>';
    }

    private function sanitizeUrl(string $url): ?string
    {
        $trimmed = trim($url);

        if ($trimmed === '' || !preg_match('#^https?://#i', $trimmed)) {
            return null;
        }

        return htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
    }
}
