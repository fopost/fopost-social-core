<?php
// phpcs:disable WordPress.WP.AlternativeFunctions -- Test files for framework-agnostic library.

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Platforms\Tumblr;

use PHPUnit\Framework\TestCase;
use Fopost\Social\Content\Post;
use Fopost\Social\Platforms\Tumblr\TumblrFormatter;

class TumblrFormatterTest extends TestCase
{
    private TumblrFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new TumblrFormatter();
    }

    public function testPlatformName(): void
    {
        $this->assertSame('tumblr', $this->formatter->platform());
    }

    public function testMaxLength(): void
    {
        $this->assertSame(4_096, $this->formatter->maxLength());
    }

    public function testFormatTitleAsHeading(): void
    {
        $post = new Post(title: 'My Post', body: 'Hello world.');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('<h2>My Post</h2>', $result);
        $this->assertStringContainsString('<p>Hello world.</p>', $result);
    }

    public function testFormatBodyOnly(): void
    {
        $post = new Post(title: '', body: 'Just body content.');

        $result = $this->formatter->format($post);

        $this->assertSame('<p>Just body content.</p>', $result);
    }

    public function testFormatWithUrl(): void
    {
        $post = new Post(title: '', body: 'Check this out', url: 'https://example.com');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('<a href="https://example.com">https://example.com</a>', $result);
    }

    public function testFormatFullPost(): void
    {
        $post = new Post(
            title: 'Launch Day',
            body: 'Our product is live!',
            url: 'https://example.com/launch',
        );

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('<h2>Launch Day</h2>', $result);
        $this->assertStringContainsString('<p>Our product is live!</p>', $result);
        $this->assertStringContainsString('<a href="https://example.com/launch">', $result);
    }

    public function testHtmlEntitiesEscaped(): void
    {
        $post = new Post(title: 'A <script> & "test"', body: '<b>Bold</b> & more');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('&lt;script&gt; &amp; &quot;test&quot;', $result);
        $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt; &amp; more', $result);
    }

    public function testMultipleParagraphs(): void
    {
        $post = new Post(title: '', body: "First paragraph.\n\nSecond paragraph.\n\nThird.");

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('<p>First paragraph.</p>', $result);
        $this->assertStringContainsString('<p>Second paragraph.</p>', $result);
        $this->assertStringContainsString('<p>Third.</p>', $result);
    }

    public function testSingleNewlinesConvertedToBr(): void
    {
        $post = new Post(title: '', body: "Line one.\nLine two.");

        $result = $this->formatter->format($post);

        $this->assertStringContainsString("Line one.<br />\nLine two.", $result);
    }

    public function testTruncatesLongText(): void
    {
        $longBody = str_repeat('A', 5000);
        $post = new Post(title: '', body: $longBody);

        $result = $this->formatter->format($post);

        $this->assertLessThanOrEqual(4_096, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    // -----------------------------------------------------------------------
    //  formatTags
    // -----------------------------------------------------------------------

    public function testFormatTagsCommaSeparated(): void
    {
        $post = new Post(title: '', body: '', tags: ['tech', 'php', 'coding']);

        $result = $this->formatter->formatTags($post);

        $this->assertSame('tech,php,coding', $result);
    }

    public function testFormatTagsStripsHashPrefix(): void
    {
        $post = new Post(title: '', body: '', tags: ['#prefixed', 'normal']);

        $result = $this->formatter->formatTags($post);

        $this->assertSame('prefixed,normal', $result);
    }

    public function testFormatTagsSkipsEmpty(): void
    {
        $post = new Post(title: '', body: '', tags: ['', '  ', 'valid']);

        $result = $this->formatter->formatTags($post);

        $this->assertSame('valid', $result);
    }

    public function testFormatTagsEmptyArray(): void
    {
        $post = new Post(title: '', body: '', tags: []);

        $result = $this->formatter->formatTags($post);

        $this->assertSame('', $result);
    }

    // -----------------------------------------------------------------------
    //  formatTitle
    // -----------------------------------------------------------------------

    public function testFormatTitle(): void
    {
        $post = new Post(title: 'Short Title', body: '');

        $this->assertSame('Short Title', $this->formatter->formatTitle($post));
    }

    public function testFormatTitleTruncatesLongTitle(): void
    {
        $longTitle = str_repeat('A', 250);
        $post = new Post(title: $longTitle, body: '');

        $result = $this->formatter->formatTitle($post);

        $this->assertLessThanOrEqual(200, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }
}
