<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;

/**
 * Fields joined into one control, under one label. Each field keeps its own
 * label for screen readers, so give it a placeholder for everyone else.
 *
 * @api
 */
class FusedGroup extends Layout
{
    protected string|Closure|null $label = null;

    /**
     * @param  array<int, Component>  $components
     */
    final public function __construct(array $components = [])
    {
        $this->schema($components);
    }

    /**
     * @param  array<int, Component>  $components
     */
    public static function make(array $components = []): static
    {
        return (new static($components))->configure();
    }

    public function getType(): string
    {
        return 'fused-group';
    }

    public function schema(array $components): static
    {
        foreach ($components as $component) {
            if ($component instanceof Field) {
                $component->hiddenLabel();
            }
        }

        return parent::schema($components);
    }

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    protected function getNodeAttributes(): array
    {
        return [...parent::getNodeAttributes(), 'label' => $this->evaluate($this->label)];
    }
}
