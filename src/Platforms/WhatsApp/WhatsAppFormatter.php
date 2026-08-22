<?php

declare(strict_types=1);

namespace Fopost\Social\Platforms\WhatsApp;

use Fopost\Social\Content\Post;
use Fopost\Social\Formatting\Contracts\FormatterInterface;

/**
 * Formats content for WhatsApp messages.
 *
 * WhatsApp supports basic formatting in text messages:
 *   - *bold*
 *   - _italic_
 *   - ~strikethrough~
 *   - ```monospace```
 *
 * Constraints:
 *   - Text message body: 4,096 characters
 *   - Image/video caption: 1,024 characters
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/messages/text-messages
 */
class WhatsAppFormatter implements FormatterInterface
{
    /**
     * Maximum text message body length.
     */
    private const MAX_TEXT_LENGTH = 4_096;

    /**
     * Maximum caption length for image/video messages.
     */
    public const MAX_CAPTION_LENGTH = 1_024;

    /**
     * Format a Post into a WhatsApp text message body.
     *
     * Structure: *Title* (bold) → Body → URL → Hashtags
     */
    public function format(Post $post, array $options = []): string
    {
        $parts = [];

        // Title in bold
        if ($post->title !== '') {
            $parts[] = '*' . $this->escapeFormatting($post->title) . '*';
        }

        // Body text
        if ($post->body !== '') {
            $parts[] = $post->body;
        }

        // URL (WhatsApp auto-previews URLs)
        if ($post->hasUrl()) {
            $parts[] = $post->url;
        }

        // Hashtags
        if ($post->tags !== []) {
            $hashtags = $this->formatHashtags($post->tags);
            if ($hashtags !== '') {
                $parts[] = $hashtags;
            }
        }

        $maxLength = ($options['as_caption'] ?? false)
            ? self::MAX_CAPTION_LENGTH
            : self::MAX_TEXT_LENGTH;

        $message = implode("\n\n", $parts);

        return $this->truncate($message, $maxLength);
    }

    /**
     * Format a caption for image/video messages (shorter limit).
     */
    public function formatCaption(Post $post, array $options = []): string
    {
        return $this->format($post, array_merge($options, ['as_caption' => true]));
    }

    /**
     * Escape characters that trigger WhatsApp formatting.
     *
     * Prevents unintentional bold/italic/strikethrough in the title.
     */
    private function escapeFormatting(string $text): string
    {
        // Remove leading/trailing formatting chars within the title itself
        // to prevent nested formatting issues
        return str_replace(['*', '_', '~', '```'], ['', '', '', ''], $text);
    }

    /**
     * Format tags as hashtags.
     */
    private function formatHashtags(array $tags): string
    {
        $hashtags = [];

        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }

            $tag = ltrim($tag, '#');
            $tag = preg_replace('/[^a-zA-Z0-9_\p{L}]/u', '', $tag);

            if ($tag !== '' && $tag !== '0') {
                $hashtags[] = '#' . $tag;
            }
        }

        return implode(' ', $hashtags);
    }

    /**
     * Truncate text to max length with ellipsis.
     */
    private function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 3) . '...';
    }

    public function platform(): string
    {
        return 'whatsapp';
    }

    public function maxLength(): int
    {
        return self::MAX_TEXT_LENGTH;
    }
}
