<?php

namespace Xelqun\Forms\Security;

/**
 * Everything the server needs to rebuild a form. It travels to the browser
 * only in encrypted form, so the client never chooses a class to instantiate.
 */
final class FormToken
{
    /**
     * @param  class-string  $formClass
     * @param  array<string, mixed>  $context
     * @param  array<int, array{k: string, s: array<string, mixed>}>  $actions  The actions to go through to reach a modal, each with the state posted to the form holding it.
     */
    public function __construct(
        public readonly string $formClass,
        public readonly int|string|null $recordKey,
        public readonly int|string|null $userId,
        public readonly array $context = [],
        public readonly ?int $issuedAt = null,
        public readonly array $actions = [],
    ) {}

    /**
     * shortcut: the parent's state travels in the token, so a very large form makes every request in its modal large. Keep it on the server if that shows up.
     *
     * @param  array<string, mixed>  $state
     */
    public function withAction(string $key, array $state): self
    {
        return new self($this->formClass, $this->recordKey, $this->userId, $this->context, $this->issuedAt, [
            ...$this->actions,
            ['k' => $key, 's' => $state],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'f' => $this->formClass,
            'r' => $this->recordKey,
            'u' => $this->userId,
            'c' => $this->context,
            't' => $this->issuedAt ?? time(),
            'a' => $this->actions,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            formClass: (string) ($payload['f'] ?? ''),
            recordKey: $payload['r'] ?? null,
            userId: $payload['u'] ?? null,
            context: is_array($payload['c'] ?? null) ? $payload['c'] : [],
            issuedAt: is_int($payload['t'] ?? null) ? $payload['t'] : null,
            actions: is_array($payload['a'] ?? null) ? $payload['a'] : [],
        );
    }
}
