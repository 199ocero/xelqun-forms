<?php

namespace Xelqun\Forms\Expressions;

/**
 * Splits an expression such as "sum(items.*.subtotal) * 1.12" into tokens.
 */
final class Lexer
{
    private const array OPERATORS = ['&&', '||', '==', '!=', '<=', '>=', '<', '>', '+', '-', '*', '/', '%', '!', '(', ')', ','];

    /**
     * @return array<int, array{type: string, value: string|int|float, position: int}>
     */
    public static function tokenize(string $source): array
    {
        $tokens = [];
        $length = strlen($source);
        $position = 0;

        while ($position < $length) {
            $character = $source[$position];

            if (ctype_space($character)) {
                $position++;

                continue;
            }

            if (ctype_digit($character) || ($character === '.' && $position + 1 < $length && ctype_digit($source[$position + 1]))) {
                preg_match('/\G(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?/', $source, $match, 0, $position);
                $text = $match[0];
                $number = (float) $text;
                $tokens[] = [
                    'type' => 'number',
                    'value' => floor($number) === $number && abs($number) < 1e15 ? (int) $number : $number,
                    'position' => $position,
                ];
                $position += strlen($text);

                continue;
            }

            if ($character === '"' || $character === "'") {
                $end = $position + 1;
                $text = '';

                while ($end < $length && $source[$end] !== $character) {
                    if ($source[$end] === '\\' && $end + 1 < $length) {
                        $end++;
                    }

                    $text .= $source[$end];
                    $end++;
                }

                if ($end >= $length) {
                    throw ExpressionException::at($source, $position, 'Unterminated string');
                }

                $tokens[] = ['type' => 'string', 'value' => $text, 'position' => $position];
                $position = $end + 1;

                continue;
            }

            if (preg_match('/\G(\$|[A-Za-z_]\w*)(\.(\*|\d+|[A-Za-z_]\w*))*/', $source, $match, 0, $position)) {
                $tokens[] = ['type' => 'identifier', 'value' => $match[0], 'position' => $position];
                $position += strlen($match[0]);

                continue;
            }

            foreach (self::OPERATORS as $operator) {
                if (substr($source, $position, strlen($operator)) === $operator) {
                    $tokens[] = ['type' => 'operator', 'value' => $operator, 'position' => $position];
                    $position += strlen($operator);

                    continue 2;
                }
            }

            throw ExpressionException::at($source, $position, "Unexpected character [{$character}]");
        }

        return $tokens;
    }
}
