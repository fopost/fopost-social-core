<?php

declare(strict_types=1);

namespace Owlstack\Core\Platforms\Pinterest;

use Owlstack\Core\Content\Post;
use Owlstack\Core\Formatting\Contracts\FormatterInterface;

/**
 * Formats content for Pinterest Pin descriptions.
 *
 * Pinterest Pins have two main text fields:
 *   - Title: max 100 characters
 *   - Description: max 800 characters (supports hashtags)
 *
 * The formatter produces a description string. The title is passed
 * separately by PinterestPlatform to the API.
 *
 * @see https://developers.pinterest.com/docs/api/v5/pins-create
 */
class PinterestFormatter implements FormatterInterface
{
    /**
     * Maximum description length.
     */
    private const MAX_DESCRIPTION_LENGTH = 800;

    /**
     * Maximum title length.
     */
    public const MAX_TITLE_LENGTH = 100;

    /**
     * Format a Post into a Pinterest description string.
     *
     * Structure: Body → URL (plain text) → Hashtags
     * Title is NOT included here — it's sent as a separate API field.
     */
    public function format(Post $post, array $options = []): string
    {
        $parts = [];

        // Body text
        if ($post->body !== '') {
            $parts[] = $post->body;
        }

        // URL as plain text in description (the link field handles clickable URL)
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

        $description = implode("\n\n", $parts);

        return $this->truncate($description, self::MAX_DESCRIPTION_LENGTH);
    }

    /**
     * Format the title, truncated to Pinterest's 100-character limit.
     */
    public function formatTitle(Post $post): string
    {
        $title = trim($post->title);

        return $this->truncate($title, self::MAX_TITLE_LENGTH);
    }

    /**
     * Format tags as Pinterest hashtags.
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
        return 'pinterest';
    }

    public function maxLength(): int
    {
        return self::MAX_DESCRIPTION_LENGTH;
    }
}
