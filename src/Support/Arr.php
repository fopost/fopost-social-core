<?php

declare(strict_types=1);

namespace Owlstack\Core\Support;

/**
 * Array utility helpers.
 */
class Arr
{
    /**
     * Get a value from a nested array using dot notation.
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Filter an array, removing null and empty string values.
     */
    public static function filterEmpty(array $array): array
    {
        return array_filter($array, fn($value) => $value !== null && $value !== '');
    }

    /**
     * Get only the specified keys from an array.
     */
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }
}
