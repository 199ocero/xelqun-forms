<?php

namespace Xelqun\Forms\Conditions;

/**
 * Text chosen by a condition, evaluated in the browser with no server call.
 *
 * Both strings are sent to the browser, so neither should hold anything the
 * user may not see.
 */
final class ConditionalText
{
    public function __construct(
        public readonly Condition $condition,
        public readonly string $then,
        public readonly ?string $else = null,
    ) {}

    public function else(?string $text): self
    {
        return new self($this->condition, $this->then, $text);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function resolve(array $state, string $container): ?string
    {
        return $this->condition->evaluate($state, $container) ? $this->then : $this->else;
    }

    /**
     * @return array{when: array<string, mixed>, text: string, otherwise: ?string}
     */
    public function toArray(string $container): array
    {
        return [
            'when' => $this->condition->toArray($container),
            'text' => $this->then,
            'otherwise' => $this->else,
        ];
    }
}
