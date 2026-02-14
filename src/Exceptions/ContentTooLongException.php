<?php

declare(strict_types=1);

namespace Owlstack\Core\Exceptions;

/**
 * Thrown when content exceeds a platform's character limit.
 */
class ContentTooLongException extends OwlstackException
{
    public function __construct(
        public readonly string $platformName,
        public readonly int $maxLength,
        public readonly int $actualLength,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Content too long for {$platformName}: {$actualLength} characters (max: {$maxLength}).",
            $code,
            $previous,
        );
    }
}
