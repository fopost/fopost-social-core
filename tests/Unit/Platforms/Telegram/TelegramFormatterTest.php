<?php

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Platforms\Telegram;

use PHPUnit\Framework\TestCase;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Formatting\CharacterTruncator;
use Owlstack\Core\Formatting\HashtagExtractor;
use Owlstack\Core\Platforms\Telegram\TelegramFormatter;

class TelegramFormatterTest extends TestCase
{
    private TelegramFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new TelegramFormatter(
            new HashtagExtractor(),
            new CharacterTruncator(),
        );
    }

    public function testFormatBasicPost(): void
    {
        $post = new Post(title: 'Hello World', body: 'This is a test post.');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('<b>Hello World</b>', $result);
        $this->assertStringContainsString('This is a test post.', $result);
    }

    public function testFormatWithUrl(): void
    {
        $post = new Post(title: 'Title', body: 'Body text', url: 'https://example.com');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('https://example.com', $result);
    }

    public function testFormatWithHashtags(): void
    {
        $post = new Post(title: 'Title', body: 'Body', tags: ['php', 'owlstack']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('#php', $result);
        $this->assertStringContainsString('#owlstack', $result);
    }

    public function testFormatCaptionMode(): void
    {
        $post = new Post(title: 'Title', body: str_repeat('A', 2000));

        $result = $this->formatter->format($post, ['is_caption' => true]);

        $this->assertLessThanOrEqual(1024, mb_strlen($result));
    }

    public function testFormatTextMode(): void
    {
        $post = new Post(title: 'Title', body: str_repeat('A', 5000));

        $result = $this->formatter->format($post, ['is_caption' => false]);

        $this->assertLessThanOrEqual(4096, mb_strlen($result));
    }

    public function testFormatEscapesHtml(): void
    {
        $post = new Post(title: 'A & B <tag>', body: 'Content with "quotes"');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('A &amp; B &lt;tag&gt;', $result);
        $this->assertStringContainsString('Content with &quot;quotes&quot;', $result);
    }

    public function testFormatEmptyTitle(): void
    {
        $post = new Post(title: '', body: 'Just body content');

        $result = $this->formatter->format($post);

        $this->assertStringNotContainsString('<b>', $result);
        $this->assertStringContainsString('Just body content', $result);
    }

    public function testPlatformName(): void
    {
        $this->assertSame('telegram', $this->formatter->platform());
    }

    public function testMaxLength(): void
    {
        $this->assertSame(4096, $this->formatter->maxLength(false));
        $this->assertSame(1024, $this->formatter->maxLength(true));
    }
}
