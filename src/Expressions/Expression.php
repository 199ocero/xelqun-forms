<?php

namespace Xelqun\Forms\Expressions;

use Xelqun\Forms\Support\StatePath;

/**
 * A parsed client-side calculation, e.g. "qty * price" or "sum(items.*.subtotal)".
 */
final class Expression
{
    /**
     * @param  array<string, mixed>  $ast
     */
    private function __construct(
        public readonly string $source,
        public readonly array $ast,
    ) {}

    public static function make(string $source): self
    {
        return new self($source, Parser::parse($source));
    }

    /**
     * The AST with every reference resolved to an absolute state path.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $container): array
    {
        return self::resolveNode($this->ast, $container);
    }

    /**
     * @return array{src: string, ast: array<string, mixed>}
     */
    public function toArray(string $container): array
    {
        return ['src' => $this->source, 'ast' => $this->resolve($container)];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function evaluate(array $state, string $container): mixed
    {
        return ExpressionEvaluator::evaluate($this->resolve($container), $state);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function resolveNode(array $node, string $container): array
    {
        if (array_key_exists('ref', $node)) {
            return ['ref' => StatePath::resolve($node['ref'], $container)];
        }

        if (array_key_exists('args', $node)) {
            $node['args'] = array_map(fn (array $child): array => self::resolveNode($child, $container), $node['args']);
        }

        return $node;
    }
}
