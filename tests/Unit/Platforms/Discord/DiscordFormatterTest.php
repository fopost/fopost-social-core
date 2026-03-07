<?php
// phpcs:disable WordPress.WP.AlternativeFunctions -- Test files for framework-agnostic library.

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Platforms\Discord;

use PHPUnit\Framework\TestCase;
use Owlstack\Core\Content\Media;
use Owlstack\Core\Content\MediaCollection;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Formatting\CharacterTruncator;
use Owlstack\Core\Formatting\HashtagExtractor;
use Owlstack\Core\Platforms\Discord\DiscordFormatter;

class DiscordFormatterTest extends TestCase
{
    private DiscordFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new DiscordFormatter(
            new HashtagExtractor(),
            new CharacterTruncator(),
        );
    }

    public function testFormatBasicPost(): void
    {
        $post = new Post(title: 'My Post', body: 'This is content.');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('**My Post**', $result);
        $this->assertStringContainsString('This is content.', $result);
    }

    public function testFormatWithUrl(): void
    {
        $post = new Post(title: 'Title', body: 'Content', url: 'https://example.com');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('https://example.com', $result);
    }

    public function testFormatWithTags(): void
    {
        $post = new Post(title: 'Title', body: 'Content', tags: ['php', 'discord']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('`php`', $result);
        $this->assertStringContainsString('`discord`', $result);
    }

    public function testFormatEmptyTitle(): void
    {
        $post = new Post(title: '', body: 'Just body content');

        $result = $this->formatter->format($post);

        $this->assertSame('Just body content', $result);
    }

    public function testFormatTruncatesLongContent(): void
    {
        $longBody = str_repeat('A', 3000);
        $post = new Post(title: 'Title', body: $longBody);

        $result = $this->formatter->format($post);

        $this->assertLessThanOrEqual(2000, mb_strlen($result));
    }

    public function testFormatEmbedMode(): void
    {
        $longBody = str_repeat('A', 3000);
        $post = new Post(title: 'Title', body: $longBody);

        $result = $this->formatter->format($post, ['is_embed' => true]);

        // Embed allows up to 4096 chars for description
        $this->assertLessThanOrEqual(4096, mb_strlen($result));
    }

    public function testFormatEmbedStructure(): void
    {
        $post = new Post(
            title: 'Embed Title',
            body: 'Embed description.',
            url: 'https://example.com',
            tags: ['tag1', 'tag2'],
        );

        $embed = $this->formatter->formatEmbed($post);

        $this->assertSame('Embed Title', $embed['title']);
        $this->assertSame('Embed description.', $embed['description']);
        $this->assertSame('https://example.com', $embed['url']);
        $this->assertSame(0x5865F2, $embed['color']);
        $this->assertStringContainsString('tag1', $embed['footer']['text']);
        $this->assertStringContainsString('tag2', $embed['footer']['text']);
        $this->assertArrayHasKey('timestamp', $embed);
    }

    public function testFormatEmbedWithImage(): void
    {
        $media = new MediaCollection([
            new Media(path: 'https://example.com/image.jpg', mimeType: 'image/jpeg'),
        ]);

        $post = new Post(title: 'Image Post', body: 'Has image', media: $media);

        $embed = $this->formatter->formatEmbed($post);

        $this->assertSame('https://example.com/image.jpg', $embed['image']['url']);
    }

    public function testFormatEmbedCustomColor(): void
    {
        $post = new Post(title: 'Colored', body: 'Content');

        $embed = $this->formatter->formatEmbed($post, ['color' => 0xFF0000]);

        $this->assertSame(0xFF0000, $embed['color']);
    }

    public function testFormatEmbedTruncatesLongDescription(): void
    {
        $longBody = str_repeat('A', 5000);
        $post = new Post(title: 'Title', body: $longBody);

        $embed = $this->formatter->formatEmbed($post);

        $this->assertLessThanOrEqual(4096, mb_strlen($embed['description']));
    }

    public function testPlatformName(): void
    {
        $this->assertSame('discord', $this->formatter->platform());
    }

    public function testMaxLength(): void
    {
        $this->assertSame(2000, $this->formatter->maxLength());
    }
}
