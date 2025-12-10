<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use function preg_replace_callback;

final readonly class PostReferenceValidator
{
    /**
     * Validates post references (>>ID) in parsed HTML content.
     * Generates proper page links for posts that exist in the thread.
     *
     * @param string $parsedHtml The already-parsed HTML content
     * @param array<int, int> $postIdToPageNumber Map of post ID to page number
     */
    public function validate(string $parsedHtml, array $postIdToPageNumber): string
    {
        return preg_replace_callback(
            '/<a class="quote-link" href="#post-(\d+)">(&gt;&gt;\d+)<\/a>/',
            static function (array $matches) use ($postIdToPageNumber): string {
                $postId = (int) $matches[1];
                $text = $matches[2];

                // If the post ID is not in the map, return just the text without the link
                if (!isset($postIdToPageNumber[$postId])) {
                    return $text;
                }

                $pageNumber = $postIdToPageNumber[$postId];

                // Generate link with page number if needed
                $pageParam = $pageNumber > 1 ? '?page=' . $pageNumber : '';

                return '<a class="quote-link" href="' . $pageParam . '#post-' . $postId . '">' . $text . '</a>';
            },
            $parsedHtml
        ) ?? $parsedHtml;
    }
}
