<?php

declare(strict_types=1);

namespace Fopost\Social\Platforms\Slack;

use Fopost\Social\Content\Post;
use Fopost\Social\Formatting\Contracts\FormatterInterface;

/**
 * Formats content for Slack using mrkdwn syntax.
 *
 * Slack uses its own "mrkdwn" flavour (not standard Markdown):
 *   - Bold:          *text*
 *   - Italic:        _text_
 *   - Strikethrough: ~text~
 *   - Inline code:   `code`
 *   - Code block:    ```code```
 *   - Link:          <url|text>
 *
 * @see https://docs.slack.dev/messaging/formatting-message-text
 */
class SlackFormatter implements FormatterInterface
{
    /**
     * Recommended limit for the `text` field.
     * Slack truncates at 40,000 but recommends ≤4,000.
     */
    private const MAX_TEXT_LENGTH = 40_000;

    /**
     * Recommended length for best readability.
     */
    private const RECOMMENDED_LENGTH = 4_000;

    /**
     * Maximum characters in a single Block Kit section text element.
     */
    private const MAX_BLOCK_TEXT_LENGTH = 3_000;

    /**
     * Format a Post into Slack mrkdwn plain-text content.
     *
     * The returned string is suitable for the `text` param of chat.postMessage.
     */
    public function format(Post $post, array $options = []): string
    {
        $parts = [];

        // Title in bold mrkdwn
        if ($post->title !== '') {
            $parts[] = '*' . $this->escapeMrkdwn($post->title) . '*';
        }

        // Body
        if ($post->body !== '') {
            $body = $post->body;
            $parts[] = $body;
        }

        // Tags as inline code
        if ($post->tags !== []) {
            $tags = array_map(
                static fn(string $tag): string => '`' . trim($tag) . '`',
                $post->tags,
            );
            $parts[] = implode(' ', $tags);
        }

        // URL as a Slack link
        if ($post->hasUrl()) {
            $parts[] = '<' . $post->url . '|Read more>';
        }

        $content = implode("\n\n", $parts);

        return $this->truncate($content, self::MAX_TEXT_LENGTH);
    }

    /**
     * Format a Post into Slack Block Kit blocks array.
     *
     * Returns an array of Block Kit block objects ready for the `blocks` param.
     *
     * @return array<int, array<string, mixed>>
     */
    public function formatBlocks(Post $post, array $options = []): array
    {
        $blocks = [];

        // Header block for the title
        if ($post->title !== '') {
            $blocks[] = [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => mb_substr($post->title, 0, 150), // Header limit: 150 chars
                    'emoji' => true,
                ],
            ];
        }

        // Section block for the body
        if ($post->body !== '') {
            $body = mb_strlen($post->body) > self::MAX_BLOCK_TEXT_LENGTH
                ? mb_substr($post->body, 0, self::MAX_BLOCK_TEXT_LENGTH - 3) . '...'
                : $post->body;

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $body,
                ],
            ];
        }

        // Context block for tags
        if ($post->tags !== []) {
            $tagText = implode(' ', array_map(
                static fn(string $tag): string => '`' . trim($tag) . '`',
                $post->tags,
            ));

            $blocks[] = [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => $tagText,
                    ],
                ],
            ];
        }

        // Section with link button
        if ($post->hasUrl()) {
            $blocks[] = [
                'type' => 'actions',
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => $options['button_text'] ?? 'Read more',
                            'emoji' => true,
                        ],
                        'url' => $post->url,
                        'action_id' => 'link_button',
                    ],
                ],
            ];
        }

        // Divider at the end if there are blocks
        if ($blocks !== []) {
            $blocks[] = ['type' => 'divider'];
        }

        return $blocks;
    }

    /**
     * Escape special mrkdwn characters in text.
     */
    private function escapeMrkdwn(string $text): string
    {
        // Escape &, <, > which have special meaning in Slack mrkdwn
        return str_replace(
            ['&', '<', '>'],
            ['&amp;', '&lt;', '&gt;'],
            $text,
        );
    }

    /**
     * Truncate text to a maximum length, appending an ellipsis if needed.
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
        return 'slack';
    }

    public function maxLength(): int
    {
        return self::MAX_TEXT_LENGTH;
    }
}
