<?php
// phpcs:disable WordPress.WP.AlternativeFunctions -- Test files for framework-agnostic library.

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Platforms\Twitter;

use PHPUnit\Framework\TestCase;
use Fopost\Social\Content\Post;
use Fopost\Social\Formatting\CharacterTruncator;
use Fopost\Social\Formatting\HashtagExtractor;
use Fopost\Social\Platforms\Twitter\TwitterFormatter;

class TwitterFormatterTest extends TestCase
{
    private TwitterFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new TwitterFormatter(
            new HashtagExtractor(),
            new CharacterTruncator(),
        );
    }

    public function testFormatBasicPost(): void
    {
        $post = new Post(title: 'Title', body: 'This is a tweet.');

        $result = $this->formatter->format($post);

        $this->assertSame('This is a tweet.', $result);
    }

    public function testFormatWithUrl(): void
    {
        $post = new Post(title: 'Title', body: 'Check this out', url: 'https://example.com');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('Check this out', $result);
        $this->assertStringContainsString('https://example.com', $result);
    }

    public function testFormatWithHashtags(): void
    {
        $post = new Post(title: 'Title', body: 'Hello world', tags: ['php', 'dev']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#php', $result);
        $this->assertStringContainsString('#dev', $result);
    }

    public function testFormatPrefersExcerptOverBody(): void
    {
        $post = new Post(title: 'Title', body: 'Full body text', excerpt: 'Short excerpt');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('Short excerpt', $result);
        $this->assertStringNotContainsString('Full body text', $result);
    }

    public function testFormatTruncatesLongBody(): void
    {
        $longBody = str_repeat('A', 300);
        $post = new Post(title: 'Title', body: $longBody);

        $result = $this->formatter->format($post);

        // Result should not exceed 280 characters
        $this->assertLessThanOrEqual(280, mb_strlen($result));
    }

    public function testFormatTruncatesWithUrlAccounting(): void
    {
        // Body of 260 chars + URL (23 t.co chars + 2 newlines) would exceed 280
        $body = str_repeat('A', 260);
        $post = new Post(title: 'Title', body: $body, url: 'https://example.com/very-long-path');

        $result = $this->formatter->format($post);

        // The actual URL chars don't count — t.co wraps at 23 chars
        // But the formatted text itself should be under or at the limit
        // accounting for the actual URL string length in output
        $this->assertStringContainsString('https://example.com/very-long-path', $result);
    }

    public function testFormatDropsHashtagsWhenNoRoom(): void
    {
        // Body that nearly fills the tweet + many long hashtags
        $body = str_repeat('A', 270);
        $post = new Post(title: '', body: $body, tags: ['verylongtag', 'anotherlongtag']);

        $result = $this->formatter->format($post);

        // Should truncate body and likely drop hashtags
        $this->assertLessThanOrEqual(280, mb_strlen($result));
    }

    public function testPlatformName(): void
    {
        $this->assertSame('twitter', $this->formatter->platform());
    }

    public function testMaxLength(): void
    {
        $this->assertSame(280, $this->formatter->maxLength());
    }
}
