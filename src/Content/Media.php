<?php

declare(strict_types=1);

namespace Fopost\Social\Content;

/**
 * Represents a media attachment (image, video, audio, document).
 */
class Media
{
    public function __construct(
        public readonly string $path,
        public readonly string $mimeType,
        public readonly ?string $altText = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?int $fileSize = null,
        public readonly ?int $duration = null,
    ) {
    }

    /**
     * Check if this media is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    /**
     * Check if this media is a video.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mimeType, 'video/');
    }

    /**
     * Check if this media is audio.
     */
    public function isAudio(): bool
    {
        return str_starts_with($this->mimeType, 'audio/');
    }

    /**
     * Check if this media is a document.
     */
    public function isDocument(): bool
    {
        return !$this->isImage() && !$this->isVideo() && !$this->isAudio();
    }
}
