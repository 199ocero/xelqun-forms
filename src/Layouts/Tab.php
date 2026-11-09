<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Illuminate\Support\Str;

/**
 * @api
 */
class Tab extends Layout
{
    protected string|Closure|null $icon = null;

    protected int|string|Closure|null $badge = null;

    protected string|Closure|null $badgeColor = null;

    final public function __construct(protected string|Closure $label) {}

    public static function make(string|Closure $label): static
    {
        return (new static($label))->configure();
    }

    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * A count or short word after the label.
     */
    public function badge(int|string|Closure|null $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    /**
     * primary, danger, info, success, warning or gray. Gray unless you choose another.
     */
    public function badgeColor(string|Closure|null $color): static
    {
        $this->badgeColor = $color;

        return $this;
    }

    /**
     * What this tab is called in the URL.
     */
    public function getSlug(): string
    {
        return Str::slug((string) $this->evaluate($this->label));
    }

    public function getType(): string
    {
        return 'tab';
    }

    protected function getNodeAttributes(): array
    {
        return [...parent::getNodeAttributes(), 'label' => $this->evaluate($this->label)];
    }

    protected function getProps(): array
    {
        return [
            ...parent::getProps(),
            'slug' => $this->getSlug(),
            ...$this->evaluateConfigured(['icon' => $this->icon, 'badge' => $this->badge, 'badgeColor' => $this->badgeColor]),
        ];
    }
}
