<?php

declare(strict_types=1);

namespace Owlstack\Core\Exceptions;

use DateTimeImmutable;

/**
 * Thrown when a platform's API rate limit is hit.
 */
class RateLimitException extends PlatformException
{
    public readonly ?DateTimeImmutable $retryAfter;

    public function __construct(
        string $message,
        string $platformName,
        ?DateTimeImmutable $retryAfter = null,
        ?int $httpStatusCode = 429,
        array $rawResponse = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $this->retryAfter = $retryAfter;

        parent::__construct(
            message: $message,
            platformName: $platformName,
            httpStatusCode: $httpStatusCode,
            rawResponse: $rawResponse,
            code: $code,
            previous: $previous,
        );
    }

    /**
     * Get the number of seconds to wait before retrying.
     */
    public function retryAfterSeconds(): ?int
    {
        if ($this->retryAfter === null) {
            return null;
        }

        return max(0, $this->retryAfter->getTimestamp() - time());
    }
}
