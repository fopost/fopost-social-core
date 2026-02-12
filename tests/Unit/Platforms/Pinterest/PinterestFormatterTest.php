<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Platforms\Pinterest;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Content\Post;
use Synglify\Core\Platforms\Pinterest\PinterestFormatter;

class PinterestFormatterTest extends TestCase
{
    private PinterestFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new PinterestFormatter();
    }

    public function testPlatformName(): void
    {
        $this->assertSame('pinterest', $this->formatter->platform());
    }

    public function testMaxLength(): void
    {
        $this->assertSame(800, $this->formatter->maxLength());
    }

    public function testFormatBodyOnly(): void
    {
        $post = new Post(title: '', body: 'A beautiful sunset.');

        $result = $this->formatter->format($post);

        $this->assertSame('A beautiful sunset.', $result);
    }

    public function testFormatDoesNotIncludeTitle(): void
    {
        $post = new Post(title: 'My Title', body: 'Body here.');

        $result = $this->formatter->format($post);

        // Title is separate in Pinterest API, not in description
        $this->assertStringNotContainsString('My Title', $result);
        $this->assertStringContainsString('Body here.', $result);
    }

    public function testFormatTitleSeparately(): void
    {
        $post = new Post(title: 'Pin Title', body: 'Description');

        $title = $this->formatter->formatTitle($post);

        $this->assertSame('Pin Title', $title);
    }

    public function testFormatTitleTruncation(): void
    {
        $longTitle = str_repeat('A', 150);
        $post = new Post(title: $longTitle, body: '');

        $title = $this->formatter->formatTitle($post);

        $this->assertLessThanOrEqual(100, mb_strlen($title));
        $this->assertStringEndsWith('...', $title);
    }

    public function testFormatIncludesUrl(): void
    {
        $post = new Post(title: '', body: 'Check this out', url: 'https://example.com');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('https://example.com', $result);
    }

    public function testFormatWithHashtags(): void
    {
        $post = new Post(title: '', body: 'Content', tags: ['home', 'decor']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#home', $result);
        $this->assertStringContainsString('#decor', $result);
    }

    public function testHashtagsSanitized(): void
    {
        $post = new Post(title: '', body: 'Body', tags: ['hello world', '#prefixed', 'special!char']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#helloworld', $result);
        $this->assertStringContainsString('#prefixed', $result);
        $this->assertStringContainsString('#specialchar', $result);
    }

    public function testTruncatesLongDescription(): void
    {
        $longBody = str_repeat('A', 1000);
        $post = new Post(title: '', body: $longBody);

        $result = $this->formatter->format($post);

        $this->assertLessThanOrEqual(800, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    public function testEmptyBodyAndTags(): void
    {
        $post = new Post(title: 'Title only', body: '');

        $result = $this->formatter->format($post);

        $this->assertSame('', $result);
    }

    public function testFullPostFormat(): void
    {
        $post = new Post(
            title: 'Pin Title',
            body: 'Great recipe for pasta.',
            url: 'https://example.com/recipe',
            tags: ['recipe', 'pasta'],
        );

        $result = $this->formatter->format($post);

        $expected = "Great recipe for pasta.\n\nhttps://example.com/recipe\n\n#recipe #pasta";
        $this->assertSame($expected, $result);
    }

    public function testEmptyTagsSkipped(): void
    {
        $post = new Post(title: '', body: 'Body', tags: ['', '  ', 'valid']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#valid', $result);
        $this->assertStringNotContainsString('# ', $result);
    }

    public function testUnicodeHashtags(): void
    {
        $post = new Post(title: '', body: 'Body', tags: ['café', 'données']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#café', $result);
        $this->assertStringContainsString('#données', $result);
    }
}
