<?php

namespace Xelqun\Forms\Layouts;

use Closure;

/**
 * @api
 */
class Fieldset extends Layout
{
    final public function __construct(protected string|Closure|null $legend = null) {}

    public static function make(string|Closure|null $legend = null): static
    {
        return (new static($legend))->configure();
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
