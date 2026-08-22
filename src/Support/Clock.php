<?php

declare(strict_types=1);

namespace Fopost\Social\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Time abstraction for testability.
 *
 * Instead of calling time() or new DateTimeImmutable() directly,
 * use this class so time can be mocked in tests.
 */
class Clock
{
    private static ?DateTimeImmutable $frozenTime = null;

    /**
     * Get the current time.
     */
    public static function now(?DateTimeZone $timezone = null): DateTimeImmutable
    {
        if (self::$frozenTime !== null) {
            return $timezone
                ? self::$frozenTime->setTimezone($timezone)
                : self::$frozenTime;
        }

        return new DateTimeImmutable('now', $timezone);
    }

    /**
     * Get the current Unix timestamp.
     */
    public static function timestamp(): int
    {
        return self::now()->getTimestamp();
    }

    /**
     * Freeze time at a specific moment (for testing).
     */
    public static function freeze(?DateTimeImmutable $time = null): void
    {
        self::$frozenTime = $time ?? new DateTimeImmutable();
    }

    /**
     * Unfreeze time (resume normal clock behavior).
     */
    public static function unfreeze(): void
    {
        self::$frozenTime = null;
    }
}
