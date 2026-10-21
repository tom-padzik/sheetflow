<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Support;

class ArrayCompat
{
    public static function all(array $array, callable $callback): bool
    {
        if (function_exists('array_all')) {
            return array_all($array, $callback);
        }

        return self::allEmulated(array: $array, callback: $callback);
    }

    public static function any(array $array, callable $callback): bool
    {
        if (function_exists('array_any')) {
            return array_any($array, $callback);
        }

        return self::anyEmulated(array: $array, callback: $callback);
    }

    public static function find(array $array, callable $callback): mixed
    {
        if (function_exists('array_find')) {
            return array_find($array, $callback);
        }

        return self::findEmulated(array: $array, callback: $callback);
    }

    public static function allEmulated(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if (! $callback($value, $key)) {
                return false;
            }
        }

        return true;
    }

    public static function anyEmulated(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }

        return false;
    }

    public static function findEmulated(array $array, callable $callback): mixed
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return null;
    }
}
