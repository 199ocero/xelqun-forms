<?php

namespace Xelqun\Forms\Components;

use Closure;

/**
 * @api
 */
class Toggle extends Checkbox
{
    protected string|Closure|null $onIcon = null;

    protected string|Closure|null $offIcon = null;

    protected string|Closure|null $onColor = null;

    protected string|Closure|null $offColor = null;

    public function getType(): string
    {
        return 'toggle';
    }

    /**
     * An icon in the knob while the toggle is on.
     */
    public function onIcon(string|Closure|null $icon): static
    {
        $this->onIcon = $icon;

        return $this;
    }

    /**
     * An icon in the knob while the toggle is off.
     */
    public function offIcon(string|Closure|null $icon): static
    {
        $this->offIcon = $icon;

        return $this;
    }

    /**
     * primary, danger, info, success, warning or gray, for the track while on.
     */
    public function onColor(string|Closure|null $color): static
    {
        $this->onColor = $color;

        return $this;
    }

    /**
     * primary, danger, info, success, warning or gray, for the track while off.
     */
    public function offColor(string|Closure|null $color): static
    {
        $this->offColor = $color;

        return $this;
    }

    protected function getProps(): array
    {
        return [
            ...parent::getProps(),
            ...$this->evaluateConfigured([
                'onIcon' => $this->onIcon,
                'offIcon' => $this->offIcon,
                'onColor' => $this->onColor,
                'offColor' => $this->offColor,
            ]),
        ];
    }
}
