<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Xelqun\Forms\Components\Component;

/**
 * What to show where there is nothing yet.
 *
 * @api
 */
class EmptyState extends Layout
{
    protected string|Closure|null $description = null;

    protected string|Closure|null $icon = null;

    protected bool|Closure $isContained = true;

    final public function __construct(protected string|Closure $heading) {}

    public static function make(string|Closure $heading): static
    {
        return (new static($heading))->configure();
    }

    public function getType(): string
    {
        return 'empty-state';
    }

    public function description(string|Closure|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Drop the border around the empty state.
     */
    public function contained(bool|Closure $condition = true): static
    {
        $this->isContained = $condition;

        return $this;
    }

    /**
     * Components shown below the text.
     *
     * @param  array<int, Component>  $components
     */
    public function footer(array $components): static
    {
        return $this->schema($components);
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
            'icon' => $this->evaluate($this->icon),
            'contained' => (bool) $this->evaluate($this->isContained),
        ];
    }
}
