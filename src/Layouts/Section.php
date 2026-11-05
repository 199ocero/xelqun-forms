<?php

namespace Xelqun\Forms\Layouts;

use Closure;

/**
 * @api
 */
class Section extends Layout
{
    protected string|Closure|null $description = null;

    protected bool $isCollapsible = false;

    protected bool $isCollapsed = false;

    final public function __construct(protected string|Closure|null $heading = null) {}

    public static function make(string|Closure|null $heading = null): static
    {
        return (new static($heading))->configure();
    }

    public function getType(): string
    {
        return 'section';
    }

    public function description(string|Closure|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function collapsible(bool $condition = true): static
    {
        $this->isCollapsible = $condition;

        return $this;
    }

    public function collapsed(bool $condition = true): static
    {
        $this->isCollapsed = $condition;
        $this->isCollapsible = $this->isCollapsible || $condition;

        return $this;
    }

    protected function getNodeAttributes(): array
    {
        return [...parent::getNodeAttributes(), 'label' => $this->evaluate($this->heading)];
    }

    protected function getProps(): array
    {
        return [
            ...parent::getProps(),
            'description' => $this->evaluate($this->description),
            'collapsible' => $this->isCollapsible,
            'collapsed' => $this->isCollapsed,
        ];
    }
}
