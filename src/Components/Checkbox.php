<?php

namespace Xelqun\Forms\Components;

use Closure;

/**
 * @api
 */
class Checkbox extends Field
{
    protected string|Closure|null $description = null;

    protected function setUp(): void
    {
        $this->default(false);
    }

    public function getType(): string
    {
        return 'checkbox';
    }

    public function description(string|Closure|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function formatStateFromRecord(mixed $value): mixed
    {
        return (bool) $value;
    }

    protected function getRequiredRule(): string
    {
        return 'accepted';
    }

    protected function getTypeRules(): array
    {
        return ['boolean'];
    }

    public function dehydrateState(mixed $value): mixed
    {
        return (bool) $value;
    }

    protected function getProps(): array
    {
        return ['description' => $this->evaluate($this->description)];
    }
}
