<?php

namespace Xelqun\Forms\Components\Concerns;

use Closure;
use Xelqun\Forms\Actions\Action;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Text;

/**
 * Extra content around a field. Each slot takes a string, a closure returning
 * one, a component or a list of components. Calling one again replaces it.
 *
 * @api
 */
trait HasSlots
{
    /**
     * @param  string|Closure|Component|array<int, Component|string>  $content
     */
    public function aboveLabel(string|Closure|Component|array $content): static
    {
        return $this->fillSlot('aboveLabel', $content);
    }

    /**
     * @param  string|Closure|Component|array<int, Component|string>  $content
     */
    public function beforeLabel(string|Closure|Component|array $content): static
    {
        return $this->fillSlot('beforeLabel', $content);
    }

    /**
     * @param  string|Closure|Component|array<int, Component|string>  $content
     */
    public function afterLabel(string|Closure|Component|array $content): static
    {
        return $this->fillSlot('afterLabel', $content);
    }

    /**
     * @param  string|Closure|Component|array<int, Component|string>  $content
     */
    public function belowLabel(string|Closure|Component|array $content): static
    {
        return $this->fillSlot('belowLabel', $content);
    }

    /**
     * @param  string|Closure|Component|array<int, Component|string>  $content
     */
    public function aboveContent(string|Closure|Component|array $content): static
    {
        return $this->fillSlot('aboveContent', $content);
    }

    /**
     * @param  string|Closure|Component|array<int, Component|string>  $content
     */
    public function beforeContent(string|Closure|Component|array $content): static
    {
        return $this->fillSlot('beforeContent', $content);
    }

    /**
     * @param  string|Closure|Component|array<int, Component|string>  $content
     */
    public function afterContent(string|Closure|Component|array $content): static
    {
        return $this->fillSlot('afterContent', $content);
    }

    /**
     * @param  string|Closure|Component|array<int, Component|string>  $content
     */
    public function belowContent(string|Closure|Component|array $content): static
    {
        return $this->fillSlot('belowContent', $content);
    }

    /**
     * @param  string|Closure|Component|array<int, Component|string>  $content
     */
    public function aboveErrorMessage(string|Closure|Component|array $content): static
    {
        return $this->fillSlot('aboveErrorMessage', $content);
    }

    /**
     * @param  string|Closure|Component|array<int, Component|string>  $content
     */
    public function belowErrorMessage(string|Closure|Component|array $content): static
    {
        return $this->fillSlot('belowErrorMessage', $content);
    }

    /**
     * An action right before the control.
     */
    public function prefixAction(Action $action): static
    {
        return $this->fillSlot('prefixAction', $action);
    }

    /**
     * @param  array<int, Action>  $actions
     */
    public function prefixActions(array $actions): static
    {
        return $this->fillSlot('prefixAction', $actions);
    }

    /**
     * An action right after the control.
     */
    public function suffixAction(Action $action): static
    {
        return $this->fillSlot('suffixAction', $action);
    }

    /**
     * @param  array<int, Action>  $actions
     */
    public function suffixActions(array $actions): static
    {
        return $this->fillSlot('suffixAction', $actions);
    }

    /**
     * @param  string|Closure|Component|array<int, Component|string>  $content
     */
    private function fillSlot(string $slot, string|Closure|Component|array $content): static
    {
        $this->slotComponents = array_values(array_filter(
            $this->slotComponents,
            fn (Component $component): bool => $component->getSlot() !== $slot,
        ));

        foreach (is_array($content) ? $content : [$content] as $item) {
            $component = $item instanceof Component ? $item : Text::make($item);

            // A slot is outside the field's state, so nothing would check or save a field in it.
            Form::eachField([$component], function (Field $field) use ($slot): void {
                throw new FormDefinitionException("The field [{$field->getName()}] can't go in the [{$slot}] slot. Slots hold display components, such as Text.");
            });

            $this->slotComponents[] = $component->inSlot($slot);
        }

        return $this;
    }
}
