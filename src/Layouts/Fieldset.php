<?php

namespace Xelqun\Forms\Layouts;

use Closure;

class Fieldset extends Layout
{
    final public function __construct(protected string|Closure|null $legend = null) {}

    public static function make(string|Closure|null $legend = null): static
    {
        return new static($legend);
    }

    public function getType(): string
    {
        return 'fieldset';
    }

    protected function getNodeAttributes(): array
    {
        return [...parent::getNodeAttributes(), 'label' => $this->evaluate($this->legend)];
    }
}
