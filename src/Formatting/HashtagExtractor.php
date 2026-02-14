<?php

declare(strict_types=1);

namespace Owlstack\Core\Formatting;

/**
 * Converts tags into platform-formatted hashtag strings.
 */
class HashtagExtractor
{
    /**
     * Convert an array of tags to a hashtag string.
     *
     * @param string[] $tags     Array of tag strings.
     * @param int      $maxCount Maximum number of hashtags to include (0 = unlimited).
     * @return string Space-separated hashtag string.
     */
    public function extract(array $tags, int $maxCount = 0): string
    {
        $hashtags = array_map(function (string $tag): string {
            $tag = trim($tag, '# ');
            // Remove spaces and special characters
            $tag = preg_replace('/[^a-zA-Z0-9_\p{L}]/u', '', $tag);
            return '#' . $tag;
        }, $tags);

        // Remove empty hashtags
        $hashtags = array_filter($hashtags, fn(string $h) => $h !== '#');

        if ($maxCount > 0) {
            $hashtags = array_slice($hashtags, 0, $maxCount);
        }

        return implode(' ', $hashtags);
    }
}
