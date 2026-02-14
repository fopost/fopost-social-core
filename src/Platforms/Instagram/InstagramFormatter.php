<?php

declare(strict_types=1);

namespace Owlstack\Core\Platforms\Instagram;

use Owlstack\Core\Content\Post;
use Owlstack\Core\Formatting\Contracts\FormatterInterface;

/**
 * Formats content for Instagram captions.
 *
 * Instagram captions have a 2,200 character limit and support up to 30 hashtags.
 * No rich text or links — just plain text with hashtags and mentions.
 *
 * @see https://developers.facebook.com/docs/instagram-platform/content-publishing
 */
class InstagramFormatter implements FormatterInterface
{
    /**
     * Maximum caption length for Instagram posts.
     */
    private const MAX_CAPTION_LENGTH = 2_200;

    /**
     * Maximum number of hashtags allowed per post.
     */
    private const MAX_HASHTAGS = 30;

    /**
     * Format a Post into an Instagram caption string.
     *
     * Structure: Title (if present) → Body → URL (plain text) → Hashtags
     */
    public function format(Post $post, array $options = []): string
    {
        $parts = [];

        // Title as first line (Instagram has no bold, just text)
        if ($post->title !== '') {
            $parts[] = $post->title;
        }

        // Body text
        if ($post->body !== '') {
            $parts[] = $post->body;
        }

        // URL as plain text (Instagram doesn't make caption URLs clickable,
        // but users still include them for context)
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

        $caption = implode("\n\n", $parts);

        return $this->truncate($caption, self::MAX_CAPTION_LENGTH);
    }

    /**
     * Format tags as Instagram hashtags, limited to 30.
     */
    private function formatHashtags(array $tags): string
    {
        $hashtags = [];

        foreach (array_slice($tags, 0, self::MAX_HASHTAGS) as $tag) {
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }

            // Remove any existing # prefix, then add it
            $tag = ltrim($tag, '#');
            // Remove spaces/special chars from hashtag
            $tag = preg_replace('/[^a-zA-Z0-9_\p{L}]/u', '', $tag);

            if ($tag !== '' && $tag !== '0') {
                $hashtags[] = '#' . $tag;
            }
        }

        return implode(' ', $hashtags);
    }

    /**
     * Truncate caption to max length with ellipsis.
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
        return 'instagram';
    }

    public function maxLength(): int
    {
        return self::MAX_CAPTION_LENGTH;
    }
}
