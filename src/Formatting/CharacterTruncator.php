<?php

declare(strict_types=1);

namespace Synglify\Core\Formatting;

/**
 * Smart text truncation that respects word boundaries.
 */
class CharacterTruncator
{
    public function __construct(
        private readonly string $ellipsis = '…',
    ) {
    }

    /**
     * Truncate text to a maximum length, respecting word boundaries.
     *
     * @param string $text      The text to truncate.
     * @param int    $maxLength Maximum character length.
     * @param string $suffix    Optional suffix to append (default: ellipsis).
     * @return string The truncated text.
     */
    public function truncate(string $text, int $maxLength, ?string $suffix = null): string
    {
        $suffix = $suffix ?? $this->ellipsis;

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $availableLength = $maxLength - mb_strlen($suffix);
        if ($availableLength <= 0) {
            return mb_substr($text, 0, $maxLength);
        }

        $truncated = mb_substr($text, 0, $availableLength);

        // Try to break at the last word boundary
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > $availableLength * 0.5) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated) . $suffix;
    }
}
