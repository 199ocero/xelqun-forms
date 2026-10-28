<?php

namespace Xelqun\Forms\Expressions;

/**
 * Recursive-descent parser that turns an expression into a JSON-ready AST.
 *
 * Node shapes: {lit: value}, {ref: path}, {op: operator, args: [...]}, {fn: name, args: [...]}.
 */
final class Parser
{
    public const array FUNCTIONS = ['sum', 'count', 'min', 'max', 'avg', 'round', 'abs', 'if'];

    /**
     * Binary operators grouped from lowest to highest precedence.
     */
    private const array PRECEDENCE = [
        ['||'],
        ['&&'],
        ['==', '!='],
        ['<', '<=', '>', '>='],
        ['+', '-'],
        ['*', '/', '%'],
    ];

    /** @var array<int, array{type: string, value: string|int|float, position: int}> */
    private array $tokens = [];

    private int $index = 0;

    private function __construct(private readonly string $source) {}

    /**
     * @return array<string, mixed>
     */
    public static function parse(string $source): array
    {
        $parser = new self($source);
        $parser->tokens = Lexer::tokenize($source);

        if ($parser->tokens === []) {
            throw new ExpressionException("Expression [{$source}] is empty.");
        }

        $ast = $parser->parseBinary(0);

        if ($parser->index < count($parser->tokens)) {
            $token = $parser->tokens[$parser->index];

            throw ExpressionException::at($source, $token['position'], "Unexpected token [{$token['value']}]");
        }

        return $ast;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseBinary(int $level): array
    {
        if ($level === count(self::PRECEDENCE)) {
            return $this->parseUnary();
        }

        $left = $this->parseBinary($level + 1);

        while ($this->peekOperator(self::PRECEDENCE[$level])) {
            $operator = (string) $this->tokens[$this->index++]['value'];
            $right = $this->parseBinary($level + 1);
            $left = ['op' => $operator, 'args' => [$left, $right]];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseUnary(): array
    {
        if ($this->peekOperator(['!'])) {
            $this->index++;

            return ['op' => '!', 'args' => [$this->parseUnary()]];
        }

        if ($this->peekOperator(['-'])) {
            $this->index++;

            return ['op' => 'neg', 'args' => [$this->parseUnary()]];
        }

        if ($this->peekOperator(['+'])) {
            $this->index++;

            return $this->parseUnary();
        }

        return $this->parsePrimary();
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePrimary(): array
    {
        $token = $this->tokens[$this->index] ?? null;

        if ($token === null) {
            throw ExpressionException::at($this->source, strlen($this->source), 'Unexpected end of expression');
        }

        $this->index++;

        if ($token['type'] === 'number' || $token['type'] === 'string') {
            return ['lit' => $token['value']];
        }

        if ($token['type'] === 'operator' && $token['value'] === '(') {
            $inner = $this->parseBinary(0);
            $this->expect(')');

            return $inner;
        }

        if ($token['type'] === 'identifier') {
            $name = (string) $token['value'];

            if ($this->peekOperator(['('])) {
                return $this->parseCall($name, $token['position']);
            }

            return match ($name) {
                'true' => ['lit' => true],
                'false' => ['lit' => false],
                'null' => ['lit' => null],
                default => ['ref' => $name],
            };
        }

        throw ExpressionException::at($this->source, $token['position'], "Unexpected token [{$token['value']}]");
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCall(string $name, int $position): array
    {
        if (! in_array($name, self::FUNCTIONS, true)) {
            throw ExpressionException::at($this->source, $position, "Unknown function [{$name}]");
        }

        $this->index++;
        $arguments = [];

        if (! $this->peekOperator([')'])) {
            do {
                $arguments[] = $this->parseBinary(0);
            } while ($this->peekOperator([',']) && ++$this->index);
        }

        $this->expect(')');

        return ['fn' => $name, 'args' => $arguments];
    }

    /**
     * @param  array<int, string>  $operators
     */
    private function peekOperator(array $operators): bool
    {
        $token = $this->tokens[$this->index] ?? null;

        return $token !== null && $token['type'] === 'operator' && in_array($token['value'], $operators, true);
    }

    private function expect(string $operator): void
    {
        if (! $this->peekOperator([$operator])) {
            $position = $this->tokens[$this->index]['position'] ?? strlen($this->source);

            throw ExpressionException::at($this->source, $position, "Expected [{$operator}]");
        }

        $this->index++;
    }
}
