<?php

declare(strict_types=1);

namespace Fopost\Social\Exceptions;

/**
 * Thrown when a media attachment fails validation (unsupported format, size too large, etc.).
 */
class MediaValidationException extends FopostException
{
    public function __construct(
        string $message,
        public readonly ?string $platformName = null,
        public readonly ?string $mimeType = null,
        public readonly ?int $fileSize = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
