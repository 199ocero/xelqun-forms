<?php

namespace Xelqun\Forms\Support;

/**
 * Helpers for dot-notation state paths such as "data.items.9f1c.price".
 */
final class StatePath
{
    public const string ROOT = 'data';

    public const string ROW_PLACEHOLDER = '{row}';

    /**
     * Resolve a path written by the developer against a container path.
     *
     * Bare names are relative to the container (the form root or the current
     * repeater row). A "$." prefix always points at the form root.
     */
    public static function resolve(string $path, string $container): string
    {
        if (str_starts_with($path, '$.')) {
            return self::ROOT.'.'.substr($path, 2);
        }

        if ($path === self::ROOT || str_starts_with($path, self::ROOT.'.')) {
            return $path;
        }

        return $container.'.'.$path;
    }

    /**
     * Check whether two paths touch the same state. Wildcards ("*") match any
     * segment, and a path overlaps every path below or above it.
     */
    public static function overlaps(string $first, string $second): bool
    {
        $firstSegments = explode('.', $first);
        $secondSegments = explode('.', $second);
        $length = min(count($firstSegments), count($secondSegments));

        for ($index = 0; $index < $length; $index++) {
            $left = $firstSegments[$index];
            $right = $secondSegments[$index];

            if ($left !== $right && $left !== '*' && $right !== '*'
                && $left !== self::ROW_PLACEHOLDER && $right !== self::ROW_PLACEHOLDER) {
                return false;
            }
        }

        return true;
    }

    /**
     * Read a value from the state. Paths containing "*" return a flat list.
     *
     * @param  array<string, mixed>  $state
     */
    public static function get(array $state, string $path): mixed
    {
        if (! str_contains($path, '*')) {
            return data_get($state, $path);
        }

        return self::collect($state, explode('.', $path));
    }

    /**
     * @param  array<int, string>  $segments
     * @return array<int, mixed>
     */
    private static function collect(mixed $value, array $segments): array
    {
        if ($segments === []) {
            return [$value];
        }

        $segment = array_shift($segments);

        if (! is_array($value)) {
            return [];
        }

        if ($segment === '*') {
            $results = [];

            foreach ($value as $item) {
                array_push($results, ...self::collect($item, $segments));
            }

            return $results;
        }

        if (! array_key_exists($segment, $value)) {
            return [];
        }

        return self::collect($value[$segment], $segments);
    }

    /**
     * Replace the row placeholder in every string of a serialized node.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    public static function replaceRow(array $value, string $rowKey): array
    {
        array_walk_recursive($value, function (mixed &$item) use ($rowKey): void {
            if (is_string($item)) {
                $item = str_replace(self::ROW_PLACEHOLDER, $rowKey, $item);
            }
        });

        return $value;
    }
}
