<?php

namespace Xelqun\Actions;

use Closure;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Layouts\Layout;

/**
 * Actions behind one button that opens a menu. With no label(), the button is
 * just an icon. Put it where an action can go.
 *
 * Each action keeps its own authorize(), and the group hides itself when the
 * viewer can use none of them.
 *
 * @api
 */
class ActionGroup extends Layout
{
    protected string|Closure|null $label = null;

    protected string|Closure|null $icon = null;

    protected string|Closure|null $color = null;

    protected string|Closure|null $tooltip = null;

    /**
     * @param  array<int, Action>  $actions
     */
    final public function __construct(array $actions = [])
    {
        $this->schema($actions);
    }

    /**
     * @param  array<int, Action>  $actions
     */
    public static function make(array $actions = []): static
    {
        return (new static($actions))->configure();
    }

    /**
     * Every action in a list that may hold groups.
     *
     * @param  array<int, Component>  $components
     * @return array<int, Action>
     */
    public static function flatten(array $components): array
    {
        $actions = [];

        foreach ($components as $component) {
            if ($component instanceof Action) {
                $actions[] = $component;
            } elseif ($component instanceof self) {
                array_push($actions, ...self::flatten($component->getChildComponents()));
            }
        }

        return $actions;
    }

    public function getType(): string
    {
        return 'action-group';
    }

    /**
     * @param  array<int, Component>  $components
     */
    public function schema(array $components): static
    {
        foreach ($components as $component) {
            if (! $component instanceof Action) {
                throw new FormDefinitionException('An action group can only hold actions, so a menu never holds another menu.');
            }
        }

        return parent::schema($components);
    }

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * primary, gray or danger.
     */
    public function color(string|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function tooltip(string|Closure|null $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function isHiddenDynamically(): bool
    {
        if (parent::isHiddenDynamically()) {
            return true;
        }

        foreach ($this->getChildComponents() as $action) {
            if (! $action->isHiddenStatically() && ! $action->isHiddenDynamically()) {
                return false;
            }
        }

        return true;
    }

    /**
     * By name, so a click from a page that's out of date finds nothing instead
     * of the action that has moved into that place. See ActionSet.
     */
    protected function getChildPosition(int $index): string
    {
        /** @var Action $action */
        $action = $this->childComponents[$index];

        return $this->position.'.'.$action->getName();
    }

    protected function getProps(): array
    {
        return $this->evaluateConfigured([
            'label' => $this->label,
            'icon' => $this->icon,
            'color' => $this->color,
            'tooltip' => $this->tooltip,
        ]);
    }
}
