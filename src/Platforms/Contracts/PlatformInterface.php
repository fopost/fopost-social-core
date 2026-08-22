<?php

declare(strict_types=1);

namespace Fopost\Social\Platforms\Contracts;

use Fopost\Social\Content\Post;
use Fopost\Social\Publishing\PublishResult;

/**
 * Contract that every social media platform must implement.
 *
 * Each platform (Telegram, Twitter/X, Facebook, etc.) provides its own
 * implementation of this interface, handling API communication,
 * authentication, and platform-specific constraints.
 */
interface PlatformInterface
{
    /**
     * Get the unique identifier for this platform (e.g., 'telegram', 'twitter', 'facebook').
     */
    public function name(): string;

    /**
     * Publish content to this platform.
     */
    public function publish(Post $post, array $options = []): PlatformResponseInterface;

    /**
     * Delete a previously published post by its external ID.
     */
    public function delete(string $externalId): bool;

    /**
     * Validate that the configured credentials are valid and working.
     */
    public function validateCredentials(): bool;

    /**
     * Get the platform's content constraints.
     *
     * @return array{
     *     max_text_length: int,
     *     max_media_count: int,
     *     supported_media_types: string[],
     *     max_media_size: int,
     * }
     */
    public function constraints(): array;
}
