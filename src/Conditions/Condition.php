<?php

namespace Xelqun\Forms\Conditions;

use InvalidArgumentException;
use Xelqun\Forms\Support\StatePath;
use Xelqun\Forms\Support\Values;

/**
 * A node of the declarative condition tree.
 *
 * Paths are stored as the developer wrote them and resolved against a
 * container path when serialized or evaluated.
 */
final class Condition
{
    public const array OPERATORS = [
        'eq', 'neq', 'in', 'notIn', 'gt', 'gte', 'lt', 'lte', 'filled', 'blank', 'and', 'or', 'not',
    ];

    /**
     * @param  array<int, Condition>  $conditions
     */
    public function __construct(
        public readonly string $operator,
        public readonly ?string $path = null,
        public readonly mixed $value = null,
        public readonly array $conditions = [],
    ) {
        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Unknown condition operator [{$operator}].");
        }
    }

    /**
     * Serialize to the JSON condition tree with absolute paths.
     *
     * @return array<string, mixed>
     */
    public function toArray(string $container): array
    {
        if (in_array($this->operator, ['and', 'or', 'not'], true)) {
            return [
                'op' => $this->operator,
                'args' => array_map(fn (Condition $condition): array => $condition->toArray($container), $this->conditions),
            ];
        }

        $node = ['op' => $this->operator, 'path' => StatePath::resolve((string) $this->path, $container)];

        if (! in_array($this->operator, ['filled', 'blank'], true)) {
            $node['value'] = $this->value;
        }

        return $node;
    }

    /**
     * Every absolute path this condition reads.
     *
     * @return array<int, string>
     */
    public function paths(string $container): array
    {
        if ($this->conditions !== []) {
            return array_values(array_unique(array_merge(
                ...array_map(fn (Condition $condition): array => $condition->paths($container), $this->conditions),
            )));
        }

        return [StatePath::resolve((string) $this->path, $container)];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function evaluate(array $state, string $container): bool
    {
        return self::evaluateTree($this->toArray($container), $state);
    }

    /**
     * Evaluate a serialized condition tree against the state.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $state
     */
    public static function evaluateTree(array $node, array $state): bool
    {
        $operator = $node['op'];

        if ($operator === 'and') {
            foreach ($node['args'] as $child) {
                if (! self::evaluateTree($child, $state)) {
                    return false;
                }
            }

            return true;
        }

        if ($operator === 'or') {
            foreach ($node['args'] as $child) {
                if (self::evaluateTree($child, $state)) {
                    return true;
                }
            }

            return false;
        }

        if ($operator === 'not') {
            return ! self::evaluateTree($node['args'][0], $state);
        }

        $actual = StatePath::get($state, $node['path']);
        $expected = $node['value'] ?? null;

        return match ($operator) {
            'eq' => Values::equals($actual, $expected),
            'neq' => ! Values::equals($actual, $expected),
            'in' => Values::in($actual, (array) $expected),
            'notIn' => ! Values::in($actual, (array) $expected),
            'gt' => Values::compare($actual, $expected) === 1,
            'gte' => in_array(Values::compare($actual, $expected), [0, 1], true),
            'lt' => Values::compare($actual, $expected) === -1,
            'lte' => in_array(Values::compare($actual, $expected), [-1, 0], true),
            'filled' => Values::isFilled($actual),
            'blank' => ! Values::isFilled($actual),
            default => throw new InvalidArgumentException('Unknown condition operator ['.json_encode($operator).'].'),
        };
    }
}
