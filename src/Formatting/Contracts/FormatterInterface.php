<?php

declare(strict_types=1);

namespace Owlstack\Core\Formatting\Contracts;

use Owlstack\Core\Content\Post;

/**
 * Contract for platform-specific content formatters.
 *
 * Each platform has different constraints (character limits, supported
 * formatting, media handling). Formatters transform a Post into a
 * platform-ready string.
 */
interface FormatterInterface
{
    /**
     * Format a Post into platform-ready content.
     *
     * @param Post  $post    The content to format.
     * @param array $options Platform-specific formatting options.
     * @return string The formatted content string.
     */
    public function format(Post $post, array $options = []): string;

    /**
     * Get the platform name this formatter is for.
     */
    public function platform(): string;

    /**
     * Get the maximum content length for this platform.
     */
    public function maxLength(): int;
}
