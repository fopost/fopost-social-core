<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Platforms\WhatsApp;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Content\Post;
use Synglify\Core\Platforms\WhatsApp\WhatsAppFormatter;

class WhatsAppFormatterTest extends TestCase
{
    private WhatsAppFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new WhatsAppFormatter();
    }

    public function testPlatformName(): void
    {
        $this->assertSame('whatsapp', $this->formatter->platform());
    }

    public function testMaxLength(): void
    {
        $this->assertSame(4_096, $this->formatter->maxLength());
    }

    public function testFormatTitleBold(): void
    {
        $post = new Post(title: 'Important Update', body: 'Details here.');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('*Important Update*', $result);
        $this->assertStringContainsString('Details here.', $result);
    }

    public function testFormatBodyOnly(): void
    {
        $post = new Post(title: '', body: 'Just a message.');

        $result = $this->formatter->format($post);

        $this->assertSame('Just a message.', $result);
    }

    public function testFormatWithUrl(): void
    {
        $post = new Post(title: '', body: 'Check this', url: 'https://example.com');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('https://example.com', $result);
    }

    public function testFormatWithHashtags(): void
    {
        $post = new Post(title: '', body: 'Content', tags: ['news', 'update']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#news', $result);
        $this->assertStringContainsString('#update', $result);
    }

    public function testFormatFullPost(): void
    {
        $post = new Post(
            title: 'Launch Day',
            body: 'Our product is live!',
            url: 'https://example.com/launch',
            tags: ['launch', 'product'],
        );

        $result = $this->formatter->format($post);

        $expected = "*Launch Day*\n\nOur product is live!\n\nhttps://example.com/launch\n\n#launch #product";
        $this->assertSame($expected, $result);
    }

    public function testTitleFormattingCharsEscaped(): void
    {
        $post = new Post(title: 'Use *bold* and _italic_', body: 'Body');

        $result = $this->formatter->format($post);

        // The title wrapping should not nest formatting
        $this->assertStringContainsString('*Use bold and italic*', $result);
    }

    public function testTruncatesLongText(): void
    {
        $longBody = str_repeat('A', 5000);
        $post = new Post(title: 'Title', body: $longBody);

        $result = $this->formatter->format($post);

        $this->assertLessThanOrEqual(4_096, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    public function testFormatCaptionShorterLimit(): void
    {
        $longBody = str_repeat('A', 2000);
        $post = new Post(title: 'Title', body: $longBody);

        $result = $this->formatter->formatCaption($post);

        $this->assertLessThanOrEqual(1_024, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    public function testFormatCaptionShortContentNotTruncated(): void
    {
        $post = new Post(title: 'Hi', body: 'Short caption');

        $result = $this->formatter->formatCaption($post);

        $this->assertStringNotContainsString('...', $result);
    }

    public function testHashtagsSanitized(): void
    {
        $post = new Post(title: '', body: 'Body', tags: ['hello world', '#prefixed', 'special!']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#helloworld', $result);
        $this->assertStringContainsString('#prefixed', $result);
        $this->assertStringContainsString('#special', $result);
    }

    public function testEmptyTagsSkipped(): void
    {
        $post = new Post(title: '', body: 'Body', tags: ['', '  ', 'valid']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#valid', $result);
    }

    public function testUnicodeHashtags(): void
    {
        $post = new Post(title: '', body: 'Body', tags: ['café', 'données']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#café', $result);
        $this->assertStringContainsString('#données', $result);
    }
}
