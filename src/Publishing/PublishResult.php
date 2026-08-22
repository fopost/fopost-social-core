<?php

declare(strict_types=1);

namespace Fopost\Social\Publishing;

use DateTimeImmutable;

/**
 * The result of a publish operation to a single platform.
 */
class PublishResult
{
    public readonly DateTimeImmutable $timestamp;

    public function __construct(
        public readonly bool $success,
        public readonly string $platformName,
        public readonly ?string $externalId = null,
        public readonly ?string $externalUrl = null,
        public readonly ?string $error = null,
        ?DateTimeImmutable $timestamp = null,
    ) {
        $this->timestamp = $timestamp ?? new DateTimeImmutable();
    }

    /**
     * Check if the publish operation failed.
     */
    public function failed(): bool
    {
        return !$this->success;
    }
}
