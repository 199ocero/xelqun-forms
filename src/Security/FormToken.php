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
     */
    public function __construct(
        public readonly string $formClass,
        public readonly int|string|null $recordKey,
        public readonly int|string|null $userId,
        public readonly array $context = [],
        public readonly ?int $issuedAt = null,
    ) {}

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
        );
    }
}
