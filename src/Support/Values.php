<?php

namespace Xelqun\Forms\Support;

use JsonSerializable;

/**
 * Value semantics shared by conditions and expressions.
 *
 * Every rule here is mirrored exactly in resources/js/core/values.ts and
 * proven by the shared conformance fixtures, so PHP and the browser always
 * agree on what "filled", "equal" or "greater than" means.
 */
final class Values
{
    private const string NUMERIC_PATTERN = '/^\s*[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?\s*$/';

    public static function isNumeric(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_float($value)) {
            return is_finite($value);
        }

        return is_string($value) && preg_match(self::NUMERIC_PATTERN, $value) === 1;
    }

    public static function toNumber(mixed $value): int|float
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return is_finite($value) ? $value : 0;
        }

        if (is_string($value) && self::isNumeric($value)) {
            return self::normalizeNumber((float) trim($value));
        }

        return 0;
    }

    /**
     * Turn whole floats into integers so PHP and JSON output match JavaScript.
     */
    public static function normalizeNumber(int|float $number): int|float
    {
        if (is_int($number)) {
            return $number;
        }

        if (! is_finite($number)) {
            return 0;
        }

        if (floor($number) === $number && abs($number) < 1e15) {
            return (int) $number;
        }

        return $number;
    }

    public static function isFilled(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    public static function isTruthy(mixed $value): bool
    {
        if ($value === null || $value === false || $value === '' || $value === []) {
            return false;
        }

        if (is_int($value) || is_float($value)) {
            return $value != 0 && ! is_nan((float) $value);
        }

        return true;
    }

    public static function equals(mixed $left, mixed $right): bool
    {
        $leftBlank = $left === null || $left === '';
        $rightBlank = $right === null || $right === '';

        if ($leftBlank || $rightBlank) {
            return $leftBlank && $rightBlank;
        }

        if (is_bool($left) || is_bool($right)) {
            return self::toBoolean($left) === self::toBoolean($right);
        }

        if (self::isNumeric($left) && self::isNumeric($right)) {
            return self::toNumber($left) == self::toNumber($right);
        }

        if (is_array($left) || is_array($right)) {
            return is_array($left) && is_array($right) && self::encode($left) === self::encode($right);
        }

        return self::toText($left) === self::toText($right);
    }

    /**
     * Compare two values. Returns -1, 0 or 1, or null when they cannot be compared.
     */
    public static function compare(mixed $left, mixed $right): ?int
    {
        if (! self::isFilled($left) || ! self::isFilled($right) || is_array($left) || is_array($right)) {
            return null;
        }

        if (self::isNumeric($left) && self::isNumeric($right)) {
            return self::toNumber($left) <=> self::toNumber($right);
        }

        return strcmp(self::toText($left), self::toText($right)) <=> 0;
    }

    /**
     * Check whether a value (or any value of a list) appears in the given list.
     *
     * @param  array<int, mixed>  $list
     */
    public static function in(mixed $value, array $list): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::in($item, $list)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($list as $candidate) {
            if (self::equals($value, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public static function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (self::isNumeric($value)) {
            return self::toNumber($value) != 0;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['true', 'on', 'yes'], true);
        }

        return self::isTruthy($value);
    }

    public static function toText(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) self::normalizeNumber($value),
            is_string($value) => $value,
            $value instanceof JsonSerializable, is_array($value) => self::encode($value),
            default => '',
        };
    }

    private static function encode(mixed $value): string
    {
        return (string) json_encode($value);
    }
}
