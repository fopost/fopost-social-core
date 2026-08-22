<?php
// phpcs:disable WordPress.WP.AlternativeFunctions -- Test files for framework-agnostic library.

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Platforms\Instagram;

use PHPUnit\Framework\TestCase;
use Fopost\Social\Content\Post;
use Fopost\Social\Platforms\Instagram\InstagramFormatter;

class InstagramFormatterTest extends TestCase
{
    private InstagramFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new InstagramFormatter();
    }

    public function testPlatformName(): void
    {
        $this->assertSame('instagram', $this->formatter->platform());
    }

    public function testMaxLength(): void
    {
        $this->assertSame(2_200, $this->formatter->maxLength());
    }

    public function testFormatTitleAndBody(): void
    {
        $post = new Post(title: 'My Title', body: 'Post body here.');

        $result = $this->formatter->format($post);

        $this->assertSame("My Title\n\nPost body here.", $result);
    }

    public function testFormatBodyOnly(): void
    {
        $post = new Post(title: '', body: 'Just the body.');

        $result = $this->formatter->format($post);

        $this->assertSame('Just the body.', $result);
    }

    public function testFormatTitleOnly(): void
    {
        $post = new Post(title: 'Title only', body: '');

        $result = $this->formatter->format($post);

        $this->assertSame('Title only', $result);
    }

    public function testFormatIncludesUrlAsPlainText(): void
    {
        $post = new Post(title: 'Title', body: 'Body', url: 'https://example.com');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('https://example.com', $result);
        $this->assertStringContainsString("Title\n\nBody\n\nhttps://example.com", $result);
    }

    public function testFormatWithHashtags(): void
    {
        $post = new Post(title: 'Title', body: 'Body', tags: ['php', 'laravel']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#php', $result);
        $this->assertStringContainsString('#laravel', $result);
    }

    public function testHashtagsStrippedOfSpecialCharacters(): void
    {
        $post = new Post(title: '', body: 'Body', tags: ['hello world', 'test!tag', '#prefixed']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#helloworld', $result);
        $this->assertStringContainsString('#testtag', $result);
        $this->assertStringContainsString('#prefixed', $result);
    }

    public function testMaxThirtyHashtags(): void
    {
        $tags = [];
        for ($i = 0; $i < 40; $i++) {
            $tags[] = "tag{$i}";
        }

        $post = new Post(title: '', body: 'Body', tags: $tags);
        $result = $this->formatter->format($post);

        // Count only hashtags that match #tagN pattern
        preg_match_all('/#tag\d+/', $result, $matches);
        $this->assertCount(30, $matches[0]);
    }

    public function testTruncatesLongContent(): void
    {
        $longBody = str_repeat('A', 3000);
        $post = new Post(title: 'Title', body: $longBody);

        $result = $this->formatter->format($post);

        $this->assertLessThanOrEqual(2_200, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    public function testContentWithinLimitNotTruncated(): void
    {
        $post = new Post(title: 'Short', body: 'Content');

        $result = $this->formatter->format($post);

        $this->assertStringNotContainsString('...', $result);
    }

    public function testEmptyTagsAreSkipped(): void
    {
        $post = new Post(title: '', body: 'Body', tags: ['', '  ', 'valid']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#valid', $result);
        // Should not have empty hashtags
        $this->assertStringNotContainsString('# ', $result);
    }

    public function testFullPostFormat(): void
    {
        $post = new Post(
            title: 'Announcement',
            body: 'We launched our product!',
            url: 'https://example.com/launch',
            tags: ['launch', 'product'],
        );

        $result = $this->formatter->format($post);

        $expected = "Announcement\n\nWe launched our product!\n\nhttps://example.com/launch\n\n#launch #product";
        $this->assertSame($expected, $result);
    }

    public function testUnicodeHashtags(): void
    {
        $post = new Post(title: '', body: 'Body', tags: ['código', 'données']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#código', $result);
        $this->assertStringContainsString('#données', $result);
    }
}
