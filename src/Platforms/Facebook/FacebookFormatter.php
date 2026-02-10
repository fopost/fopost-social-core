<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Facebook;

use Synglify\Core\Content\Post;
use Synglify\Core\Formatting\Contracts\FormatterInterface;
use Synglify\Core\Formatting\CharacterTruncator;
use Synglify\Core\Formatting\HashtagExtractor;

/**
 * Formats content for Facebook's Graph API.
 *
 * Facebook supports long-form text (up to ~63,206 characters),
 * links with OG previews, and hashtag formatting.
 *
 * Note: URLs are not appended to the message text because Facebook
 * handles link previews separately via the 'link' parameter in the
 * Graph API request.
 */
class FacebookFormatter implements FormatterInterface
{
    private const MAX_POST_LENGTH = 63206;

    public function __construct(
        private readonly HashtagExtractor $hashtagExtractor,
        private readonly CharacterTruncator $truncator,
    ) {
    }

    public function format(Post $post, array $options = []): string
    {
        $parts = [];

        // Title (Facebook doesn't have native bold, but title as first line is standard)
        if ($post->title !== '') {
            $parts[] = $post->title;
        }

        // Body text
        if ($post->body !== '') {
            $parts[] = $post->body;
        }

        // Hashtags (Facebook allows many hashtags)
        if ($post->tags !== []) {
            $hashtags = $this->hashtagExtractor->extract($post->tags);
            if ($hashtags !== '') {
                $parts[] = $hashtags;
            }
        }

        $text = implode("\n\n", $parts);

        // Truncate if exceeding the generous limit
        if (mb_strlen($text) > self::MAX_POST_LENGTH) {
            $text = $this->truncator->truncate($text, self::MAX_POST_LENGTH);
        }

        return $text;
    }

    public function platform(): string
    {
        return 'facebook';
    }

    public function maxLength(): int
    {
        return self::MAX_POST_LENGTH;
    }
}
