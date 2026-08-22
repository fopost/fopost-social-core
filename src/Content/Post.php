<?php

declare(strict_types=1);

namespace Fopost\Social\Content;

/**
 * Represents a piece of content to be published to social platforms.
 *
 * This is the central value object in Owlstack. Framework packages
 * construct Post instances from their own content models (Eloquent,
 * WP_Post, etc.) and pass them to the Publisher.
 */
class Post
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly ?string $excerpt = null,
        public readonly ?MediaCollection $media = null,
        public readonly array $tags = [],
        public readonly array $metadata = [],
    ) {
    }

    /**
     * Check if this post has media attachments.
     */
    public function hasMedia(): bool
    {
        return $this->media !== null && !$this->media->isEmpty();
    }

    /**
     * Check if this post has a canonical URL.
     */
    public function hasUrl(): bool
    {
        return $this->url !== null && $this->url !== '';
    }

    /**
     * Get a specific metadata value by key.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
