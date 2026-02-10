<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Platforms\Facebook;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Content\Post;
use Synglify\Core\Formatting\CharacterTruncator;
use Synglify\Core\Formatting\HashtagExtractor;
use Synglify\Core\Platforms\Facebook\FacebookFormatter;

class FacebookFormatterTest extends TestCase
{
    private FacebookFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new FacebookFormatter(
            new HashtagExtractor(),
            new CharacterTruncator(),
        );
    }

    public function testFormatBasicPost(): void
    {
        $post = new Post(title: 'My Post', body: 'This is content.');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('My Post', $result);
        $this->assertStringContainsString('This is content.', $result);
    }

    public function testFormatWithHashtags(): void
    {
        $post = new Post(title: 'Title', body: 'Content', tags: ['social', 'media']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#social', $result);
        $this->assertStringContainsString('#media', $result);
    }

    public function testFormatDoesNotAppendUrl(): void
    {
        $post = new Post(title: 'Title', body: 'Body', url: 'https://example.com');

        $result = $this->formatter->format($post);

        // Facebook handles links via the separate 'link' parameter
        $this->assertStringNotContainsString('https://example.com', $result);
    }

    public function testFormatEmptyTitle(): void
    {
        $post = new Post(title: '', body: 'Just body content');

        $result = $this->formatter->format($post);

        $this->assertSame('Just body content', $result);
    }

    public function testFormatTruncatesExtremelyLongContent(): void
    {
        $longBody = str_repeat('A', 70000);
        $post = new Post(title: 'Title', body: $longBody);

        $result = $this->formatter->format($post);

        $this->assertLessThanOrEqual(63206, mb_strlen($result));
    }

    public function testPlatformName(): void
    {
        $this->assertSame('facebook', $this->formatter->platform());
    }

    public function testMaxLength(): void
    {
        $this->assertSame(63206, $this->formatter->maxLength());
    }
}
