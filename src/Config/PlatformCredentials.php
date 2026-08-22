<?php

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Framework-agnostic library; exceptions are not WordPress output.

namespace Fopost\Social\Config;

/**
 * Value object representing API credentials for a platform.
 *
 * Each platform has different credential requirements (API keys, tokens,
 * secrets, etc.). This is a keyed bag that stores them generically.
 */
class PlatformCredentials
{
    /**
     * @param string               $platform    Platform name (e.g., 'telegram', 'twitter').
     * @param array<string, mixed> $credentials Key-value credential pairs.
     */
    public function __construct(
        public readonly string $platform,
        private readonly array $credentials = [],
    ) {
    }

    /**
     * Get a credential value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }

    /**
     * Check if a credential key exists and is not empty.
     */
    public function has(string $key): bool
    {
        return isset($this->credentials[$key]) && $this->credentials[$key] !== '';
    }

    /**
     * Get all credentials as an array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->credentials;
    }

    /**
     * Get required credential or throw.
     *
     * @throws \InvalidArgumentException If the credential is missing.
     */
    public function require(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new \InvalidArgumentException(
                "Missing required credential '{$key}' for platform '{$this->platform}'."
            );
        }

        return $this->credentials[$key];
    }
}
