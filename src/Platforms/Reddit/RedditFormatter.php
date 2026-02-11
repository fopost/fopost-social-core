<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Reddit;

use Synglify\Core\Content\Post;
use Synglify\Core\Formatting\Contracts\FormatterInterface;
use Synglify\Core\Formatting\CharacterTruncator;
use Synglify\Core\Formatting\HashtagExtractor;

/**
 * Formats content for Reddit's submission constraints.
 *
 * Reddit supports self-posts (text) with Markdown formatting and
 * link-posts (URL only, no body text). Self-post body can be up to
 * 40,000 characters. Titles are limited to 300 characters.
 *
 * Reddit does not use hashtags natively, but flair-like tag references
 * can be appended at the bottom of self-posts for discoverability.
 */
class RedditFormatter implements FormatterInterface
{
    private const MAX_TITLE_LENGTH = 300;
    private const MAX_BODY_LENGTH = 40000;

    public function __construct(
        private readonly HashtagExtractor $hashtagExtractor,
        private readonly CharacterTruncator $truncator,
    ) {
    }

    public function format(Post $post, array $options = []): string
    {
        $parts = [];

        // Body text (Markdown-compatible)
        if ($post->body !== '') {
            $parts[] = $post->body;
        }

        // URL reference in body (for self-posts that also have a link)
        if ($post->hasUrl()) {
            $parts[] = '[Read more](' . $post->url . ')';
        }

        // Tags as a footer line (Reddit doesn't have hashtags but tags can add context)
        if ($post->tags !== []) {
            $tags = array_map(
                fn(string $tag): string => trim($tag),
                $post->tags,
            );
            $tagLine = 'Tags: ' . implode(', ', $tags);
            $parts[] = '---' . "\n" . $tagLine;
        }

        $text = implode("\n\n", $parts);

        // Truncate if exceeding the limit
        if (mb_strlen($text) > self::MAX_BODY_LENGTH) {
            $text = $this->truncator->truncate($text, self::MAX_BODY_LENGTH);
        }

        return $text;
    }

    /**
     * Format the post title for Reddit submission.
     *
     * Reddit titles are limited to 300 characters and cannot contain
     * Markdown formatting.
     */
    public function formatTitle(Post $post): string
    {
        $title = $post->title;

        if ($title === '') {
            // Fall back to a truncated body if no title provided
            $title = $post->body !== '' ? $post->body : 'Untitled';
        }

        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            $title = $this->truncator->truncate($title, self::MAX_TITLE_LENGTH);
        }

        return $title;
    }

    public function platform(): string
    {
        return 'reddit';
    }

    public function maxLength(): int
    {
        return self::MAX_BODY_LENGTH;
    }

    /**
     * Get the maximum title length.
     */
    public function maxTitleLength(): int
    {
        return self::MAX_TITLE_LENGTH;
    }
}
