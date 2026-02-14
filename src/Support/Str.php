<?php

declare(strict_types=1);

namespace Owlstack\Core\Support;

/**
 * String utility helpers.
 */
class Str
{
    /**
     * Truncate a string to a given length at a word boundary.
     */
    public static function limit(string $value, int $limit = 100, string $end = '…'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        $truncated = mb_substr($value, 0, $limit - mb_strlen($end));
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated) . $end;
    }

    /**
     * Convert a string to a URL-friendly slug.
     */
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9\s-]/u', '', $value);
        $value = preg_replace('/[\s-]+/', $separator, $value);

        return trim($value, $separator);
    }

    /**
     * Check if a string starts with a given substring.
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }
}
