<?php

declare(strict_types=1);

namespace Fopost\Social\Platforms\LinkedIn;

use Fopost\Social\Content\Post;
use Fopost\Social\Formatting\Contracts\FormatterInterface;
use Fopost\Social\Formatting\CharacterTruncator;
use Fopost\Social\Formatting\HashtagExtractor;

/**
 * Formats content for LinkedIn's API.
 *
 * LinkedIn supports up to 3,000 characters for organic posts.
 * Hashtags are widely used and appear at the end of the post.
 * URLs are handled inline in the commentary text for the shares API.
 */
class LinkedInFormatter implements FormatterInterface
{
    private const MAX_POST_LENGTH = 3000;

    public function __construct(
        private readonly HashtagExtractor $hashtagExtractor,
        private readonly CharacterTruncator $truncator,
    ) {
    }

    public function format(Post $post, array $options = []): string
    {
        $parts = [];

        // Title as the first line (LinkedIn has no native bold in API posts)
        if ($post->title !== '') {
            $parts[] = $post->title;
        }

        // Body text
        if ($post->body !== '') {
            $parts[] = $post->body;
        }

        // Canonical URL (LinkedIn renders link previews from URLs in text)
        if ($post->hasUrl()) {
            $parts[] = $post->url;
        }

        // Hashtags
        if ($post->tags !== []) {
            $hashtags = $this->hashtagExtractor->extract($post->tags);
            if ($hashtags !== '') {
                $parts[] = $hashtags;
            }
        }

        $text = implode("\n\n", $parts);

        // Truncate if exceeding the limit
        if (mb_strlen($text) > self::MAX_POST_LENGTH) {
            $text = $this->truncator->truncate($text, self::MAX_POST_LENGTH);
        }

        return $text;
    }

    public function platform(): string
    {
        return 'linkedin';
    }

    public function maxLength(): int
    {
        return self::MAX_POST_LENGTH;
    }
}
