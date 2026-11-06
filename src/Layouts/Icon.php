<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Xelqun\Forms\Components\Component;

/**
 * An icon from the browser's icon set. Screen readers skip it, unless it has
 * a tooltip or extraAttributes(['aria-label' => '...']).
 *
 * @api
 */
class Icon extends Component
{
    protected string|Closure|null $color = null;

    protected string|Closure|null $size = null;

    protected string|Closure|null $tooltip = null;

    final public function __construct(protected string|Closure $icon) {}

    public static function make(string|Closure $icon): static
    {
        return (new static($icon))->configure();
    }

    public function getType(): string
    {
        return 'icon';
    }

    /**
     * neutral, primary, danger, info, success or warning. Muted by default.
     */
    public function color(string|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * xs, sm, md, lg, xl or 2xl.
     */
    public function size(string|Closure|null $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function tooltip(string|Closure|null $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    protected function getProps(): array
    {
        return [
            'icon' => $this->evaluate($this->icon),
            ...$this->evaluateConfigured([
                'color' => $this->color,
                'size' => $this->size,
                'tooltip' => $this->tooltip,
            ]),
        ];
    }
}
