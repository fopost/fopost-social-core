<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Telegram;

use Synglify\Core\Content\Post;
use Synglify\Core\Formatting\Contracts\FormatterInterface;

/**
 * Formats content for Telegram's constraints and features.
 *
 * Telegram supports HTML and Markdown formatting, up to 4096 characters
 * for text messages and 1024 characters for captions.
 */
class TelegramFormatter implements FormatterInterface
{
    private const MAX_TEXT_LENGTH = 4096;
    private const MAX_CAPTION_LENGTH = 1024;

    public function format(Post $post, array $options = []): string
    {
        // TODO: implement — build message text with HTML formatting,
        //       handle caption vs text mode, inject canonical link
        $text = $post->body;

        if ($post->hasUrl()) {
            $text .= "\n\n" . $post->url;
        }

        return $text;
    }

    public function platform(): string
    {
        return 'telegram';
    }

    public function maxLength(bool $isCaption = false): int
    {
        return $isCaption ? self::MAX_CAPTION_LENGTH : self::MAX_TEXT_LENGTH;
    }
}
