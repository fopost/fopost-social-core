<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Platforms\LinkedIn;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Content\Post;
use Synglify\Core\Formatting\CharacterTruncator;
use Synglify\Core\Formatting\HashtagExtractor;
use Synglify\Core\Platforms\LinkedIn\LinkedInFormatter;

class LinkedInFormatterTest extends TestCase
{
    private LinkedInFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new LinkedInFormatter(
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

    public function testFormatIncludesUrl(): void
    {
        $post = new Post(title: 'Title', body: 'Body', url: 'https://example.com');

        $result = $this->formatter->format($post);

        // LinkedIn includes URLs inline in the commentary for link previews
        $this->assertStringContainsString('https://example.com', $result);
    }

    public function testFormatEmptyTitle(): void
    {
        $post = new Post(title: '', body: 'Just body content');

        $result = $this->formatter->format($post);

        $this->assertSame('Just body content', $result);
    }

    public function testFormatTruncatesExtremelyLongContent(): void
    {
        $longBody = str_repeat('A', 4000);
        $post = new Post(title: 'Title', body: $longBody);

        $result = $this->formatter->format($post);

        $this->assertLessThanOrEqual(3000, mb_strlen($result));
    }

    public function testFormatUrlBeforeHashtags(): void
    {
        $post = new Post(
            title: 'Title',
            body: 'Body',
            url: 'https://example.com',
            tags: ['php'],
        );

        $result = $this->formatter->format($post);

        $urlPos = mb_strpos($result, 'https://example.com');
        $hashtagPos = mb_strpos($result, '#php');

        $this->assertNotFalse($urlPos);
        $this->assertNotFalse($hashtagPos);
        $this->assertLessThan($hashtagPos, $urlPos, 'URL should appear before hashtags');
    }

    public function testPlatformName(): void
    {
        $this->assertSame('linkedin', $this->formatter->platform());
    }

    public function testMaxLength(): void
    {
        $this->assertSame(3000, $this->formatter->maxLength());
    }
}
