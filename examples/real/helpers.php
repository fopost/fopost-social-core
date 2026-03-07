<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

/**
 * Shared helpers for real API examples.
 *
 * Provides environment variable loading and result printing utilities
 * used across all platform examples.
 */

declare(strict_types=1);

use Owlstack\Core\Platforms\Contracts\PlatformResponseInterface;

/**
 * Require an environment variable or exit with a helpful message.
 *
 * @param string $name The environment variable name
 * @return string The environment variable value
 */
function requireEnv(string $name): string
{
    $value = getenv($name);

    if ($value === false || $value === '') {
        echo "\n  ✗ Missing required environment variable: {$name}\n";
        echo "    Set it with: export {$name}=your-value\n";
        echo "    See .env.example for all required variables.\n\n";
        exit(1);
    }

    return $value;
}

/**
 * Get an optional environment variable, returning a default if not set.
 *
 * @param string $name    The environment variable name
 * @param string $default The default value
 * @return string
 */
function optionalEnv(string $name, string $default = ''): string
{
    $value = getenv($name);

    return ($value !== false && $value !== '') ? $value : $default;
}

/**
 * Print the result of a publish operation in a consistent format.
 *
 * @param PlatformResponseInterface $result The platform response
 */
function printResult(PlatformResponseInterface $result): void
{
    if ($result->isSuccess()) {
        echo "  ✓ Published successfully!\n";
        echo "    External ID:  " . ($result->externalId() ?? 'N/A') . "\n";
        echo "    External URL: " . ($result->externalUrl() ?? 'N/A') . "\n";
    } else {
        echo "  ✗ Publishing failed.\n";
        echo "    Error: " . ($result->error() ?? 'Unknown error') . "\n";
    }
}

/**
 * Print platform constraints in a readable format.
 *
 * @param array $constraints The constraints array from the platform
 */
function printConstraints(array $constraints): void
{
    foreach ($constraints as $key => $value) {
        $display = is_array($value) ? implode(', ', $value) : (string) $value;
        echo "    {$key}: {$display}\n";
    }
}
