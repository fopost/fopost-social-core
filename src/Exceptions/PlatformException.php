<?php

declare(strict_types=1);

namespace Owlstack\Core\Exceptions;

/**
 * Thrown when a platform API returns an error.
 */
class PlatformException extends OwlstackException
{
    public function __construct(
        string $message,
        public readonly string $platformName,
        public readonly ?int $httpStatusCode = null,
        public readonly ?string $apiErrorCode = null,
        public readonly array $rawResponse = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
