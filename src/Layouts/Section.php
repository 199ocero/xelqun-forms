<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;

/**
 * @api
 */
class Section extends Layout
{
    protected string|Closure|null $description = null;

    protected string|Closure|null $icon = null;

    protected bool|Closure $isCollapsible = false;

    protected bool|Closure $isCollapsed = false;

    protected bool|Closure $persistsCollapsed = false;

    protected bool|Closure $isAside = false;

    protected bool|Closure $isCompact = false;

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

    /**
     * An icon before the heading.
     */
    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function collapsible(bool|Closure $condition = true): static
    {
        $this->isCollapsible = $condition;

        return $this;
    }

    /**
     * Start collapsed. This makes the section collapsible.
     */
    public function collapsed(bool|Closure $condition = true): static
    {
        $this->isCollapsed = $condition;

        return $this;
    }

    /**
     * Remember in the browser whether the viewer collapsed it.
     */
    public function persistCollapsed(bool|Closure $condition = true): static
    {
        $this->persistsCollapsed = $condition;

        return $this;
    }

    /**
     * The heading and description in a column beside the card, on wide screens.
     */
    public function aside(bool|Closure $condition = true): static
    {
        $this->isAside = $condition;

        return $this;
    }

    /**
     * Less padding, for a section among many.
     */
    public function compact(bool|Closure $condition = true): static
    {
        $this->isCompact = $condition;

        return $this;
    }

    /**
     * Actions or text at the end of the header.
     *
     * @param  array<int, Component>|Component  $components
     */
    public function afterHeader(array|Component $components): static
    {
        return $this->fillSectionSlot('afterHeader', $components);
    }

    /**
     * Actions or text at the bottom of the card.
     *
     * @param  array<int, Component>|Component  $components
     */
    public function footer(array|Component $components): static
    {
        return $this->fillSectionSlot('footer', $components);
    }

    /**
     * @param  array<int, Component>|Component  $components
     */
    private function fillSectionSlot(string $slot, array|Component $components): static
    {
        $this->slotComponents = array_values(array_filter($this->slotComponents, fn (Component $component): bool => $component->getSlot() !== $slot));

        foreach (is_array($components) ? $components : [$components] as $component) {
            // Outside the section's fields, so nothing would check or save a field here.
            Form::eachField([$component], function (Field $field) use ($slot): void {
                throw new FormDefinitionException("The field [{$field->getName()}] can't go in a section's [{$slot}]. Put actions or text there.");
            });

            $this->slotComponents[] = $component->inSlot($slot);
        }

        return $this;
    }

    protected function getNodeAttributes(): array
    {
        return [...parent::getNodeAttributes(), 'label' => $this->evaluate($this->heading)];
    }

    protected function getProps(): array
    {
        $isCollapsed = (bool) $this->evaluate($this->isCollapsed);

        return [
            ...parent::getProps(),
            'description' => $this->evaluate($this->description),
            'collapsible' => $isCollapsed || (bool) $this->evaluate($this->isCollapsible),
            'collapsed' => $isCollapsed,
            ...$this->evaluateConfigured([
                'icon' => $this->icon,
                'persistCollapsed' => $this->persistsCollapsed,
                'aside' => $this->isAside,
                'compact' => $this->isCompact,
            ]),
        ];
    }
}
