<?php
// phpcs:disable WordPress.WP.AlternativeFunctions -- Test files for framework-agnostic library.

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Platforms\Reddit;

use PHPUnit\Framework\TestCase;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Formatting\CharacterTruncator;
use Owlstack\Core\Formatting\HashtagExtractor;
use Owlstack\Core\Platforms\Reddit\RedditFormatter;

class RedditFormatterTest extends TestCase
{
    private RedditFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new RedditFormatter(
            new HashtagExtractor(),
            new CharacterTruncator(),
        );
    }

    public function testFormatBasicPost(): void
    {
        $post = new Post(title: 'My Post', body: 'This is the content.');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('This is the content.', $result);
    }

    public function testFormatWithUrl(): void
    {
        $post = new Post(title: 'Title', body: 'Content', url: 'https://example.com');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('[Read more](https://example.com)', $result);
    }

    public function testFormatWithTags(): void
    {
        $post = new Post(title: 'Title', body: 'Content', tags: ['php', 'reddit']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('Tags: php, reddit', $result);
        $this->assertStringContainsString('---', $result);
    }

    public function testFormatEmptyBody(): void
    {
        $post = new Post(title: 'Title Only', body: '');

        $result = $this->formatter->format($post);

        $this->assertSame('', $result);
    }

    public function testFormatTruncatesLongContent(): void
    {
        $longBody = str_repeat('A', 50000);
        $post = new Post(title: 'Title', body: $longBody);

        $result = $this->formatter->format($post);

        $this->assertLessThanOrEqual(40000, mb_strlen($result));
    }

    public function testFormatTitleBasic(): void
    {
        $post = new Post(title: 'My Reddit Post', body: 'Content');

        $result = $this->formatter->formatTitle($post);

        $this->assertSame('My Reddit Post', $result);
    }

    public function testFormatTitleFallsBackToBody(): void
    {
        $post = new Post(title: '', body: 'This is the body text');

        $result = $this->formatter->formatTitle($post);

        $this->assertSame('This is the body text', $result);
    }

    public function testFormatTitleFallsBackToUntitled(): void
    {
        $post = new Post(title: '', body: '');

        $result = $this->formatter->formatTitle($post);

        $this->assertSame('Untitled', $result);
    }

    public function testFormatTitleTruncatesLongTitle(): void
    {
        $longTitle = str_repeat('A', 400);
        $post = new Post(title: $longTitle, body: 'Content');

        $result = $this->formatter->formatTitle($post);

        $this->assertLessThanOrEqual(300, mb_strlen($result));
    }

    public function testPlatformName(): void
    {
        $this->assertSame('reddit', $this->formatter->platform());
    }

    public function testMaxLength(): void
    {
        $this->assertSame(40000, $this->formatter->maxLength());
    }

    public function testMaxTitleLength(): void
    {
        $this->assertSame(300, $this->formatter->maxTitleLength());
    }
}
