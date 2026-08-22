<?php

declare(strict_types=1);

namespace Fopost\Social\Platforms\Telegram;

use Fopost\Social\Content\Post;
use Fopost\Social\Formatting\Contracts\FormatterInterface;
use Fopost\Social\Formatting\CharacterTruncator;
use Fopost\Social\Formatting\HashtagExtractor;

/**
 * Formats content for Telegram's constraints and features.
 *
 * Telegram supports HTML and Markdown formatting, up to 4096 characters
 * for text messages and 1024 characters for captions.
 */
class TelegramFormatter implements FormatterInterface
{
    private const MAX_TEXT_LENGTH = 4096;
    private const MAX_CAPTION_LENGTH = 1024;

    public function __construct(
        private readonly HashtagExtractor $hashtagExtractor,
        private readonly CharacterTruncator $truncator,
    ) {
    }

    public function format(Post $post, array $options = []): string
    {
        $isCaption = $options['is_caption'] ?? false;
        $maxLength = $isCaption ? self::MAX_CAPTION_LENGTH : self::MAX_TEXT_LENGTH;

        $parts = [];

        // Title in bold HTML
        if ($post->title !== '') {
            $parts[] = '<b>' . $this->escapeHtml($post->title) . '</b>';
        }

        // Body text
        if ($post->body !== '') {
            $parts[] = $this->escapeHtml($post->body);
        }

        // Hashtags
        if ($post->tags !== []) {
            $hashtags = $this->hashtagExtractor->extract($post->tags);
            if ($hashtags !== '') {
                $parts[] = $hashtags;
            }
        }

        // Canonical URL
        if ($post->hasUrl()) {
            $parts[] = $post->url;
        }

        $text = implode("\n\n", $parts);

        // Truncate if exceeding the limit
        if (mb_strlen($text) > $maxLength) {
            $text = $this->truncatePreservingStructure($post, $maxLength, $isCaption);
        }

        return $text;
    }

    public function platform(): string
    {
        return 'telegram';
    }

    public function maxLength(bool $isCaption = false): int
    {
        return $isCaption ? self::MAX_CAPTION_LENGTH : self::MAX_TEXT_LENGTH;
    }

    /**
     * Truncate the formatted text while preserving URL and hashtags at the end.
     */
    private function truncatePreservingStructure(Post $post, int $maxLength, bool $isCaption): string
    {
        $suffix = '';

        // Always preserve URL at the end
        if ($post->hasUrl()) {
            $suffix = "\n\n" . $post->url;
        }

        // Try to preserve hashtags
        $hashtags = '';
        if ($post->tags !== []) {
            $hashtags = "\n\n" . $this->hashtagExtractor->extract($post->tags);
        }

        $reservedLength = mb_strlen($suffix) + mb_strlen($hashtags);
        $availableForBody = $maxLength - $reservedLength;

        // If not enough room for hashtags, drop them
        if ($availableForBody < 50) {
            $hashtags = '';
            $reservedLength = mb_strlen($suffix);
            $availableForBody = $maxLength - $reservedLength;
        }

        // Build the truncatable portion (title + body)
        $bodyParts = [];
        if ($post->title !== '') {
            $bodyParts[] = '<b>' . $this->escapeHtml($post->title) . '</b>';
        }
        if ($post->body !== '') {
            $bodyParts[] = $this->escapeHtml($post->body);
        }

        $bodyText = implode("\n\n", $bodyParts);
        $truncatedBody = $this->truncator->truncate($bodyText, $availableForBody);

        return $truncatedBody . $hashtags . $suffix;
    }

    /**
     * Escape HTML special characters for Telegram's HTML parse mode.
     */
    private function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
