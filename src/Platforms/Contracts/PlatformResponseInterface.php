<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Contracts;

/**
 * Represents the response from a platform after a publish operation.
 */
interface PlatformResponseInterface
{
    /**
     * Whether the publish operation was successful.
     */
    public function success(): bool;

    /**
     * The external ID assigned by the platform (e.g., tweet ID, message ID).
     */
    public function externalId(): ?string;

    /**
     * The URL of the published content on the platform (if available).
     */
    public function externalUrl(): ?string;

    /**
     * The raw response data from the platform API.
     */
    public function rawResponse(): array;

    /**
     * Error message if the publish operation failed.
     */
    public function errorMessage(): ?string;
}
