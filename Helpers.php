<?php
declare(strict_types=1);

/**
 * Access nested array easily
 * phpcs:disable
 */
if (!function_exists('array_get')) {
    function array_get(array $array, string $key, $default = null)
    {
        $value = fn($default) => $default instanceof \Closure ? $default() : $default;
        if (!is_array($array)) {
            return $value($array);
        }

        if (is_null($key)) {
            return $array;
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (strpos($key, '.') === false) {
            return $array[$key] ?? $value($default);
        }

        foreach (explode('.', $key) as $segment) {
            if ((is_array($array) || $array instanceof ArrayAccess) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $value($default);
            }
        }

        return $array;
    }
}

/**
 * Get only keys from given array
 */
if (!function_exists('array_only')) {
    function array_only(array $array, array $keys): array {
        return array_intersect_key($array, array_flip((array) $keys));
    }
}

/**
 * Get Edara URL
 */
if (!function_exists('edara_url')) {
    function edara_url(): string {
        return EDARA_INTEGRATION_PLUGIN_IS_PRODUCTION ? 'https://startsolutionsandbox.edara.io' : 'https://startsolutionsandbox.edara.io';
    }
}

// phpcs:enable