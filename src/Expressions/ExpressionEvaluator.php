<?php

namespace Xelqun\Forms\Expressions;

use Xelqun\Forms\Support\StatePath;
use Xelqun\Forms\Support\Values;

/**
 * Evaluates expression ASTs. Mirrored by resources/js/core/expressions.ts.
 */
final class ExpressionEvaluator
{
    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $state
     */
    public static function evaluate(array $node, array $state): mixed
    {
        $result = self::evaluateNode($node, $state);

        return is_int($result) || is_float($result) ? Values::normalizeNumber($result) : $result;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $state
     */
    private static function evaluateNode(array $node, array $state): mixed
    {
        if (array_key_exists('lit', $node)) {
            return $node['lit'];
        }

        if (array_key_exists('ref', $node)) {
            return StatePath::get($state, $node['ref']);
        }

        if (array_key_exists('fn', $node)) {
            return self::call($node['fn'], $node['args'], $state);
        }

        $operator = $node['op'];
        $arguments = $node['args'];

        if ($operator === '&&') {
            return Values::isTruthy(self::evaluateNode($arguments[0], $state))
                && Values::isTruthy(self::evaluateNode($arguments[1], $state));
        }

        if ($operator === '||') {
            return Values::isTruthy(self::evaluateNode($arguments[0], $state))
                || Values::isTruthy(self::evaluateNode($arguments[1], $state));
        }

        if ($operator === '!') {
            return ! Values::isTruthy(self::evaluateNode($arguments[0], $state));
        }

        if ($operator === 'neg') {
            return -Values::toNumber(self::evaluateNode($arguments[0], $state));
        }

        $left = self::evaluateNode($arguments[0], $state);
        $right = self::evaluateNode($arguments[1], $state);

        return match ($operator) {
            '==' => Values::equals($left, $right),
            '!=' => ! Values::equals($left, $right),
            '<' => Values::compare($left, $right) === -1,
            '<=' => in_array(Values::compare($left, $right), [-1, 0], true),
            '>' => Values::compare($left, $right) === 1,
            '>=' => in_array(Values::compare($left, $right), [0, 1], true),
            default => self::arithmetic($operator, Values::toNumber($left), Values::toNumber($right)),
        };
    }

    private static function arithmetic(string $operator, int|float $left, int|float $right): int|float
    {
        return match ($operator) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $right == 0 ? 0 : $left / $right,
            '%' => $right == 0 ? 0 : fmod($left, $right),
            default => throw new ExpressionException("Unknown operator [{$operator}]."),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $arguments
     * @param  array<string, mixed>  $state
     */
    private static function call(string $function, array $arguments, array $state): mixed
    {
        if ($function === 'if') {
            $condition = Values::isTruthy(self::evaluateNode($arguments[0], $state));

            return self::evaluateNode($arguments[$condition ? 1 : 2] ?? ['lit' => null], $state);
        }

        $values = array_map(fn (array $argument): mixed => self::evaluateNode($argument, $state), $arguments);

        if ($function === 'round') {
            $precision = (int) Values::toNumber($values[1] ?? 0);
            $number = Values::toNumber($values[0] ?? 0);
            $factor = 10 ** $precision;
            $sign = $number < 0 ? -1 : 1;

            return $sign * floor(abs($number) * $factor + 0.5) / $factor;
        }

        if ($function === 'abs') {
            return abs(Values::toNumber($values[0] ?? 0));
        }

        $items = self::flatten($values);

        if ($function === 'count') {
            return count($items);
        }

        $numbers = array_map(Values::toNumber(...), $items);

        return match ($function) {
            'sum' => array_sum($numbers),
            'avg' => $numbers === [] ? 0 : array_sum($numbers) / count($numbers),
            'min' => $numbers === [] ? 0 : min($numbers),
            'max' => $numbers === [] ? 0 : max($numbers),
            default => throw new ExpressionException("Unknown function [{$function}]."),
        };
    }

    /**
     * Spread list arguments (wildcard results, multi-select values) one level deep.
     *
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     */
    private static function flatten(array $values): array
    {
        $items = [];

        foreach ($values as $value) {
            if (is_array($value)) {
                array_push($items, ...array_values($value));

                continue;
            }

            $items[] = $value;
        }

        return $items;
    }
}
