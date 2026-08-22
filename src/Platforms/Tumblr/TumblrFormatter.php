<?php

declare(strict_types=1);

namespace Fopost\Social\Platforms\Tumblr;

use Fopost\Social\Content\Post;
use Fopost\Social\Formatting\Contracts\FormatterInterface;

/**
 * Formats content for Tumblr posts using Neue Post Format (NPF).
 *
 * Tumblr uses HTML for text blocks in NPF. This formatter produces
 * an HTML body suitable for the `content` array of NPF posts.
 *
 * Tumblr doesn't enforce a strict character limit on post body,
 * but a practical limit of 4,096 characters is used for text blocks.
 *
 * Tags are handled separately by TumblrPlatform (comma-separated).
 *
 * @see https://www.tumblr.com/docs/npf
 * @see https://www.tumblr.com/docs/en/api/v2
 */
class TumblrFormatter implements FormatterInterface
{
    /**
     * Practical max body length for text blocks.
     */
    private const MAX_TEXT_LENGTH = 4_096;

    /**
     * Maximum post title length.
     */
    public const MAX_TITLE_LENGTH = 200;

    /**
     * Format a Post into an HTML body for a Tumblr text block.
     *
     * Structure: <h2>Title</h2> → <p>Body</p> → <p><a href="...">URL</a></p>
     * Tags are NOT included here — they are passed as a separate API field.
     */
    public function format(Post $post, array $options = []): string
    {
        $parts = [];

        // Title as heading
        if ($post->title !== '') {
            $parts[] = '<h2>' . $this->escape($post->title) . '</h2>';
        }

        // Body as paragraph(s)
        if ($post->body !== '') {
            // Split on double newlines for multiple paragraphs
            $paragraphs = preg_split('/\n{2,}/', $post->body);
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if ($paragraph !== '') {
                    // Convert single newlines to <br>
                    $parts[] = '<p>' . nl2br($this->escape($paragraph)) . '</p>';
                }
            }
        }

        // URL as link
        if ($post->hasUrl()) {
            $escapedUrl = $this->escape($post->url);
            $parts[] = '<p><a href="' . $escapedUrl . '">' . $escapedUrl . '</a></p>';
        }

        $html = implode('', $parts);

        return $this->truncate($html, self::MAX_TEXT_LENGTH);
    }

    /**
     * Format tags as a comma-separated string for the Tumblr API.
     *
     * @return string Comma-separated tags without # prefix.
     */
    public function formatTags(Post $post): string
    {
        if ($post->tags === []) {
            return '';
        }

        $tags = [];
        foreach ($post->tags as $tag) {
            $tag = trim($tag);
            $tag = ltrim($tag, '#');

            if ($tag !== '' && $tag !== '0') {
                $tags[] = $tag;
            }
        }

        return implode(',', $tags);
    }

    /**
     * Format a plain-text title for Tumblr (no HTML).
     */
    public function formatTitle(Post $post): string
    {
        $title = trim($post->title);

        return $this->truncate($title, self::MAX_TITLE_LENGTH);
    }

    /**
     * Escape HTML special characters.
     */
    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
        return 'tumblr';
    }

    public function maxLength(): int
    {
        return self::MAX_TEXT_LENGTH;
    }
}
