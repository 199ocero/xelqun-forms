<?php

namespace Xelqun\Forms\Layouts;

use Closure;

class Tabs extends Layout
{
    final public function __construct(protected string|Closure|null $label = null) {}

    public static function make(string|Closure|null $label = null): static
    {
        return new static($label);
    }

    /**
     * @param  array<int, Tab>  $tabs
     */
    public function tabs(array $tabs): static
    {
        return $this->schema($tabs);
    }

    public function getType(): string
    {
        return 'tabs';
    }

    protected function getNodeAttributes(): array
    {
        return [...parent::getNodeAttributes(), 'label' => $this->evaluate($this->label)];
    }
}
