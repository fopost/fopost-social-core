<?php

declare(strict_types=1);

namespace Owlstack\Core\Platforms;

use Owlstack\Core\Platforms\Contracts\PlatformResponseInterface;

/**
 * Default implementation of PlatformResponseInterface.
 */
class PlatformResponse implements PlatformResponseInterface
{
    public function __construct(
        private readonly bool $isSuccess,
        private readonly ?string $externalId = null,
        private readonly ?string $externalUrl = null,
        private readonly array $rawResponse = [],
        private readonly ?string $errorMessage = null,
    ) {
    }

    /**
     * Create a successful response.
     */
    public static function success(string $externalId, ?string $externalUrl = null, array $rawResponse = []): self
    {
        return new self(
            isSuccess: true,
            externalId: $externalId,
            externalUrl: $externalUrl,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Create a failed response.
     */
    public static function failure(string $errorMessage, array $rawResponse = []): self
    {
        return new self(
            isSuccess: false,
            errorMessage: $errorMessage,
            rawResponse: $rawResponse,
        );
    }

    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    public function externalId(): ?string
    {
        return $this->externalId;
    }

    public function externalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function rawResponse(): array
    {
        return $this->rawResponse;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
