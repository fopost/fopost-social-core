<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Facebook;

use Synglify\Core\Content\Post;
use Synglify\Core\Formatting\Contracts\FormatterInterface;

/**
 * Formats content for Facebook's Graph API.
 *
 * Facebook supports long-form text (up to ~63,206 characters),
 * links with OG previews, and hashtag formatting.
 */
class FacebookFormatter implements FormatterInterface
{
    private const MAX_POST_LENGTH = 63206;

    public function format(Post $post, array $options = []): string
    {
        // TODO: implement — build post text, add hashtags, handle link previews
        $text = $post->body;

        if ($post->hasUrl()) {
            $text .= "\n\n" . $post->url;
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
