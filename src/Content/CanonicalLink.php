<?php

declare(strict_types=1);

namespace Fopost\Social\Content;

/**
 * Handles generating and injecting canonical URLs into published content.
 *
 * Ensures every social media post links back to the original content
 * on the user's website for SEO purposes.
 */
class CanonicalLink
{
    public function __construct(
        private readonly string $template = "\n\nRead more: {url}",
    ) {
    }

    /**
     * Generate the canonical link text for a given URL.
     */
    public function generate(string $url): string
    {
        return str_replace('{url}', $url, $this->template);
    }

    /**
     * Inject the canonical link into content text, respecting a maximum length.
     *
     * If the combined text would exceed maxLength, the content is truncated
     * to make room for the canonical link.
     */
    public function inject(string $content, string $url, int $maxLength): string
    {
        $link = $this->generate($url);
        $linkLength = mb_strlen($link);

        if (mb_strlen($content) + $linkLength <= $maxLength) {
            return $content . $link;
        }

        $availableLength = $maxLength - $linkLength;
        if ($availableLength <= 0) {
            return $link;
        }

        $truncated = mb_substr($content, 0, $availableLength - 1) . '…';

        return $truncated . $link;
    }
}
