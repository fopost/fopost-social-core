<?php

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Framework-agnostic library; exceptions are not WordPress output.

namespace Fopost\Social\Platforms;

use Fopost\Social\Platforms\Contracts\PlatformInterface;
use Fopost\Social\Exceptions\FopostException;

/**
 * Registry that holds all available platform instances.
 *
 * Framework packages register platforms into this registry at boot time.
 */
class PlatformRegistry
{
    /** @var array<string, PlatformInterface> */
    private array $platforms = [];

    /**
     * Register a platform instance.
     */
    public function register(PlatformInterface $platform): void
    {
        $this->platforms[$platform->name()] = $platform;
    }

    /**
     * Get a platform by name.
     *
     * @throws FopostException If the platform is not registered.
     */
    public function get(string $name): PlatformInterface
    {
        if (!isset($this->platforms[$name])) {
            throw new FopostException("Platform '{$name}' is not registered.");
        }

        return $this->platforms[$name];
    }

    /**
     * Check if a platform is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->platforms[$name]);
    }

    /**
     * Get all registered platform names.
     *
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->platforms);
    }

    /**
     * Get all registered platform instances.
     *
     * @return array<string, PlatformInterface>
     */
    public function all(): array
    {
        return $this->platforms;
    }
}
