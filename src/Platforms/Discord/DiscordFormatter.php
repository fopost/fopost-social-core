<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Discord;

use Synglify\Core\Content\Post;
use Synglify\Core\Formatting\Contracts\FormatterInterface;
use Synglify\Core\Formatting\CharacterTruncator;
use Synglify\Core\Formatting\HashtagExtractor;

/**
 * Formats content for Discord's message constraints.
 *
 * Discord supports Markdown formatting in messages. Regular messages
 * are limited to 2,000 characters. Embed descriptions can be up to
 * 4,096 characters, with a total embed character limit of 6,000.
 *
 * This formatter produces plain Markdown text for standard messages.
 * Embed formatting is handled in DiscordPlatform when embeds are used.
 */
class DiscordFormatter implements FormatterInterface
{
    private const MAX_MESSAGE_LENGTH = 2000;
    private const MAX_EMBED_DESCRIPTION = 4096;

    public function __construct(
        private readonly HashtagExtractor $hashtagExtractor,
        private readonly CharacterTruncator $truncator,
    ) {
    }

    public function format(Post $post, array $options = []): string
    {
        $isEmbed = $options['is_embed'] ?? false;
        $maxLength = $isEmbed ? self::MAX_EMBED_DESCRIPTION : self::MAX_MESSAGE_LENGTH;

        $parts = [];

        // Title in bold Markdown
        if ($post->title !== '') {
            $parts[] = '**' . $post->title . '**';
        }

        // Body text
        if ($post->body !== '') {
            $parts[] = $post->body;
        }

        // Tags as inline labels
        if ($post->tags !== []) {
            $tags = array_map(
                fn(string $tag): string => '`' . trim($tag) . '`',
                $post->tags,
            );
            $parts[] = implode(' ', $tags);
        }

        // URL
        if ($post->hasUrl()) {
            $parts[] = $post->url;
        }

        $text = implode("\n\n", $parts);

        // Truncate if exceeding the limit
        if (mb_strlen($text) > $maxLength) {
            $text = $this->truncatePreservingUrl($post, $maxLength);
        }

        return $text;
    }

    /**
     * Build a Discord embed structure from a Post.
     *
     * @return array<string, mixed> Discord embed object.
     */
    public function formatEmbed(Post $post, array $options = []): array
    {
        $embed = [];

        if ($post->title !== '') {
            $embed['title'] = mb_substr($post->title, 0, 256);
        }

        if ($post->body !== '') {
            $description = $post->body;
            if (mb_strlen($description) > self::MAX_EMBED_DESCRIPTION) {
                $description = $this->truncator->truncate($description, self::MAX_EMBED_DESCRIPTION);
            }
            $embed['description'] = $description;
        }

        if ($post->hasUrl()) {
            $embed['url'] = $post->url;
        }

        // Color (optional, defaults to a neutral blue)
        $embed['color'] = $options['color'] ?? 0x5865F2; // Discord blurple

        // Tags as footer
        if ($post->tags !== []) {
            $tagText = implode(' | ', $post->tags);
            $embed['footer'] = ['text' => mb_substr($tagText, 0, 2048)];
        }

        // Image from first media item
        if ($post->hasMedia()) {
            $media = $post->media->first();
            if ($media->isImage()) {
                $embed['image'] = ['url' => $media->path];
            }
        }

        // Timestamp
        $embed['timestamp'] = (new \DateTimeImmutable())->format('c');

        return $embed;
    }

    public function platform(): string
    {
        return 'discord';
    }

    public function maxLength(): int
    {
        return self::MAX_MESSAGE_LENGTH;
    }

    /**
     * Truncate text while preserving the URL at the end.
     */
    private function truncatePreservingUrl(Post $post, int $maxLength): string
    {
        $suffix = '';

        if ($post->hasUrl()) {
            $suffix = "\n\n" . $post->url;
        }

        $availableForBody = $maxLength - mb_strlen($suffix);

        if ($availableForBody < 50) {
            $suffix = '';
            $availableForBody = $maxLength;
        }

        $bodyParts = [];
        if ($post->title !== '') {
            $bodyParts[] = '**' . $post->title . '**';
        }
        if ($post->body !== '') {
            $bodyParts[] = $post->body;
        }

        $bodyText = implode("\n\n", $bodyParts);
        $truncatedBody = $this->truncator->truncate($bodyText, $availableForBody);

        return $truncatedBody . $suffix;
    }
}
