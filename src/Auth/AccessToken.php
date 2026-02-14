<?php

declare(strict_types=1);

namespace Owlstack\Core\Auth;

use DateTimeImmutable;

/**
 * Represents an OAuth access token.
 */
class AccessToken
{
    public function __construct(
        public readonly string $token,
        public readonly ?string $refreshToken = null,
        public readonly ?DateTimeImmutable $expiresAt = null,
        public readonly array $scopes = [],
        public readonly array $metadata = [],
    ) {
    }

    /**
     * Check if this token has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
    }

    /**
     * Check if this token can be refreshed.
     */
    public function isRefreshable(): bool
    {
        return $this->refreshToken !== null && $this->refreshToken !== '';
    }
}
