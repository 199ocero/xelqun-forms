<?php

namespace Xelqun\Forms\Conditions;

use BackedEnum;

/**
 * Builder for declarative conditions evaluated in the browser and re-checked on the server.
 *
 * Paths are relative to the field's container (the form root or the current
 * repeater row). Prefix a path with "$." to reach the form root from a row.
 */
final class When
{
    public static function eq(string $path, mixed $value): Condition
    {
        return new Condition('eq', $path, self::scalar($value));
    }

    public static function neq(string $path, mixed $value): Condition
    {
        return new Condition('neq', $path, self::scalar($value));
    }

    /**
     * @param  array<int, mixed>  $values
     */
    public static function in(string $path, array $values): Condition
    {
        return new Condition('in', $path, array_map(self::scalar(...), array_values($values)));
    }

    /**
     * @param  array<int, mixed>  $values
     */
    public static function notIn(string $path, array $values): Condition
    {
        return new Condition('notIn', $path, array_map(self::scalar(...), array_values($values)));
    }

    public static function gt(string $path, int|float|string $value): Condition
    {
        return new Condition('gt', $path, $value);
    }

    public static function gte(string $path, int|float|string $value): Condition
    {
        return new Condition('gte', $path, $value);
    }

    public static function lt(string $path, int|float|string $value): Condition
    {
        return new Condition('lt', $path, $value);
    }

    public static function lte(string $path, int|float|string $value): Condition
    {
        return new Condition('lte', $path, $value);
    }

    public static function filled(string $path): Condition
    {
        return new Condition('filled', $path);
    }

    public static function blank(string $path): Condition
    {
        return new Condition('blank', $path);
    }

    public static function and(Condition ...$conditions): Condition
    {
        return new Condition('and', conditions: array_values($conditions));
    }

    public static function or(Condition ...$conditions): Condition
    {
        return new Condition('or', conditions: array_values($conditions));
    }

    public static function not(Condition $condition): Condition
    {
        return new Condition('not', conditions: [$condition]);
    }

    private static function scalar(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
