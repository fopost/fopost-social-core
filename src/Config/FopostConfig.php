<?php

declare(strict_types=1);

namespace Fopost\Social\Config;

/**
 * Central configuration object for FoPost.
 *
 * Holds all settings: registered platform credentials, default options,
 * and feature flags. Framework packages populate this from their own
 * config systems (Laravel config, WP options, env files, etc.).
 */
class FopostConfig
{
    /** @var array<string, PlatformCredentials> */
    private array $platforms = [];

    /** @var array<string, mixed> */
    private array $options;

    /**
     * @param array<string, array> $platforms Keyed by platform name, value is credentials array.
     * @param array<string, mixed> $options   Global options.
     */
    public function __construct(array $platforms = [], array $options = [])
    {
        foreach ($platforms as $name => $credentials) {
            $this->platforms[$name] = $credentials instanceof PlatformCredentials
                ? $credentials
                : new PlatformCredentials($name, $credentials);
        }

        $this->options = $options;
    }

    /**
     * Get credentials for a specific platform.
     */
    public function credentials(string $platform): ?PlatformCredentials
    {
        return $this->platforms[$platform] ?? null;
    }

    /**
     * Check if a platform has credentials configured.
     */
    public function hasPlatform(string $platform): bool
    {
        return isset($this->platforms[$platform]);
    }

    /**
     * Get all configured platform names.
     *
     * @return string[]
     */
    public function configuredPlatforms(): array
    {
        return array_keys($this->platforms);
    }

    /**
     * Get a global option value.
     */
    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
