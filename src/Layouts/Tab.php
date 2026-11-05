<?php

namespace Xelqun\Forms\Layouts;

use Closure;

/**
 * @api
 */
class Tab extends Layout
{
    final public function __construct(protected string|Closure $label) {}

    public static function make(string|Closure $label): static
    {
        return (new static($label))->configure();
    }

    public function getType(): string
    {
        return 'tab';
    }

    protected function getNodeAttributes(): array
    {
        return [...parent::getNodeAttributes(), 'label' => $this->evaluate($this->label)];
    }
}
