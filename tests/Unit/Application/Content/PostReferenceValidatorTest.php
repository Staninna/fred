<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Content;

use Fred\Application\Content\PostReferenceValidator;
use Tests\TestCase;

final class PostReferenceValidatorTest extends TestCase
{
    public function testKeepsValidPostReferenceOnSamePage(): void
    {
        $validator = new PostReferenceValidator();
        $input = '<a class="quote-link" href="#post-1">&gt;&gt;1</a>';
        $postIdToPageNumber = [1 => 1, 2 => 1, 3 => 1];

        $output = $validator->validate($input, $postIdToPageNumber);

        $this->assertStringContainsString('<a class="quote-link" href="#post-1">&gt;&gt;1</a>', $output);
    }

    public function testGeneratesCorrectPageLinkForDifferentPage(): void
    {
        $validator = new PostReferenceValidator();
        $input = '<a class="quote-link" href="#post-5">&gt;&gt;5</a>';
        $postIdToPageNumber = [1 => 1, 2 => 1, 5 => 2];

        $output = $validator->validate($input, $postIdToPageNumber);

        $this->assertStringContainsString('<a class="quote-link" href="?page=2#post-5">&gt;&gt;5</a>', $output);
    }

    public function testRemovesInvalidPostReference(): void
    {
        $validator = new PostReferenceValidator();
        $input = '<a class="quote-link" href="#post-99">&gt;&gt;99</a>';
        $postIdToPageNumber = [1 => 1, 2 => 1, 3 => 1];

        $output = $validator->validate($input, $postIdToPageNumber);

        $this->assertStringNotContainsString('<a', $output);
        $this->assertStringContainsString('&gt;&gt;99', $output);
    }

    public function testHandlesMultipleReferences(): void
    {
        $validator = new PostReferenceValidator();
        $input = 'Reply to <a class="quote-link" href="#post-1">&gt;&gt;1</a> and <a class="quote-link" href="#post-50">&gt;&gt;50</a>';
        $postIdToPageNumber = [1 => 1, 2 => 1, 3 => 1];

        $output = $validator->validate($input, $postIdToPageNumber);

        // First reference is valid
        $this->assertStringContainsString('<a class="quote-link" href="#post-1">&gt;&gt;1</a>', $output);
        // Second reference is invalid
        $this->assertStringNotContainsString('<a class="quote-link" href="#post-50">', $output);
        $this->assertStringContainsString('&gt;&gt;50', $output);
    }

    public function testHandlesMultipleValidReferencesOnDifferentPages(): void
    {
        $validator = new PostReferenceValidator();
        $input = 'Reply to <a class="quote-link" href="#post-1">&gt;&gt;1</a> and <a class="quote-link" href="#post-26">&gt;&gt;26</a>';
        $postIdToPageNumber = [1 => 1, 2 => 1, 26 => 2, 27 => 2];

        $output = $validator->validate($input, $postIdToPageNumber);

        // First reference is on page 1
        $this->assertStringContainsString('<a class="quote-link" href="#post-1">&gt;&gt;1</a>', $output);
        // Second reference is on page 2
        $this->assertStringContainsString('<a class="quote-link" href="?page=2#post-26">&gt;&gt;26</a>', $output);
    }

    public function testHandlesEmptyPostIdMap(): void
    {
        $validator = new PostReferenceValidator();
        $input = '<a class="quote-link" href="#post-1">&gt;&gt;1</a>';
        $postIdToPageNumber = [];

        $output = $validator->validate($input, $postIdToPageNumber);

        // No valid posts, reference should be removed
        $this->assertStringNotContainsString('<a', $output);
        $this->assertStringContainsString('&gt;&gt;1', $output);
    }

    public function testPreservesOtherContent(): void
    {
        $validator = new PostReferenceValidator();
        $input = '<p>This is a reply to <a class="quote-link" href="#post-1">&gt;&gt;1</a> with <strong>bold text</strong>.</p>';
        $postIdToPageNumber = [1 => 1, 2 => 1];

        $output = $validator->validate($input, $postIdToPageNumber);

        $this->assertStringContainsString('<p>This is a reply to', $output);
        $this->assertStringContainsString('<strong>bold text</strong>', $output);
        $this->assertStringContainsString('<a class="quote-link" href="#post-1">&gt;&gt;1</a>', $output);
    }
}
