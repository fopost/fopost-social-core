<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Twitter;

use Synglify\Core\Content\Post;
use Synglify\Core\Formatting\Contracts\FormatterInterface;
use Synglify\Core\Formatting\CharacterTruncator;

/**
 * Formats content for Twitter/X's 280-character limit.
 *
 * Handles URL shortening (t.co counts as 23 characters), hashtag formatting,
 * and smart truncation.
 */
class TwitterFormatter implements FormatterInterface
{
    private const MAX_TWEET_LENGTH = 280;
    private const TCO_URL_LENGTH = 23;

    public function format(Post $post, array $options = []): string
    {
        // TODO: implement — build tweet text, account for t.co URL length,
        //       add hashtags, truncate if needed
        $text = $post->body;

        if ($post->hasUrl()) {
            $text .= "\n\n" . $post->url;
        }

        return $text;
    }

    public function platform(): string
    {
        return 'twitter';
    }

    public function maxLength(): int
    {
        return self::MAX_TWEET_LENGTH;
    }
}
