<?php

namespace Xelqun\Forms\Components;

use Closure;

/**
 * Escape hatch: render a Vue component registered with createXelqunForms({ components }).
 */
class CustomField extends Field
{
    protected string $component = '';

    /** @var array<string, mixed>|Closure */
    protected array|Closure $componentProps = [];

    public function getType(): string
    {
        return $this->component !== '' ? $this->component : 'custom';
    }

    public function component(string $name): static
    {
        $this->component = $name;

        return $this;
    }

    /**
     * @param  array<string, mixed>|Closure  $props
     */
    public function props(array|Closure $props): static
    {
        $this->componentProps = $props;

        return $this;
    }

    protected function getProps(): array
    {
        return (array) $this->evaluate($this->componentProps);
    }
}
