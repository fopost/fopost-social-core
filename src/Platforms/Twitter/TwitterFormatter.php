<?php

declare(strict_types=1);

namespace Fopost\Social\Platforms\Twitter;

use Fopost\Social\Content\Post;
use Fopost\Social\Formatting\Contracts\FormatterInterface;
use Fopost\Social\Formatting\CharacterTruncator;
use Fopost\Social\Formatting\HashtagExtractor;

/**
 * Formats content for Twitter/X's 280-character limit.
 *
 * Handles URL shortening (t.co counts as 23 characters), hashtag formatting,
 * and smart truncation.
 */
class TwitterFormatter implements FormatterInterface
{
    private const MAX_TWEET_LENGTH = 280;

    /**
     * All URLs posted to Twitter are wrapped in t.co and count as this many characters.
     *
     * @see https://developer.x.com/en/docs/counting-characters
     */
    private const TCO_URL_LENGTH = 23;

    public function __construct(
        private readonly HashtagExtractor $hashtagExtractor,
        private readonly CharacterTruncator $truncator,
    ) {
    }

    public function format(Post $post, array $options = []): string
    {
        $maxLength = self::MAX_TWEET_LENGTH;

        // Calculate space reserved for URL (t.co wrapping)
        $urlReserved = 0;
        if ($post->hasUrl()) {
            // URL + preceding double newline
            $urlReserved = self::TCO_URL_LENGTH + 2;
        }

        // Build hashtag string and calculate its length
        $hashtags = '';
        $hashtagsReserved = 0;
        if ($post->tags !== []) {
            $hashtags = $this->hashtagExtractor->extract($post->tags);
            if ($hashtags !== '') {
                // Hashtags + preceding double newline
                $hashtagsReserved = mb_strlen($hashtags) + 2;
            }
        }

        // Available space for body text
        $availableForBody = $maxLength - $urlReserved - $hashtagsReserved;

        // If hashtags don't fit, progressively reduce them
        if ($availableForBody < 30 && $hashtagsReserved > 0) {
            $hashtags = '';
            $hashtagsReserved = 0;
            $availableForBody = $maxLength - $urlReserved;
        }

        // Build the body: prefer excerpt for tweets, fall back to body
        $body = ($post->excerpt !== null && $post->excerpt !== '')
            ? $post->excerpt
            : $post->body;

        // Truncate the body if needed
        if (mb_strlen($body) > $availableForBody) {
            $body = $this->truncator->truncate($body, $availableForBody);
        }

        // Assemble the tweet
        $parts = [$body];

        if ($hashtags !== '') {
            $parts[] = $hashtags;
        }

        if ($post->hasUrl()) {
            $parts[] = $post->url;
        }

        return implode("\n\n", $parts);
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
