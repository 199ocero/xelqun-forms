<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Xelqun\Forms\Components\Component;

/**
 * A piece of text. A closure can read state with $get and updates with it.
 *
 * @api
 */
class Text extends Component
{
    protected bool|Closure $isBadge = false;

    protected string|Closure|null $color = null;

    protected string|Closure|null $icon = null;

    protected string|Closure|null $size = null;

    protected string|Closure|null $weight = null;

    protected string|Closure|null $fontFamily = null;

    protected string|Closure|null $tooltip = null;

    /**
     * An Htmlable reaches the page unescaped, like {!! !!} in Blade, so it must
     * never carry unescaped user input.
     */
    final public function __construct(protected string|Htmlable|Closure|null $content) {}

    public static function make(string|Htmlable|Closure|null $content): static
    {
        return (new static($content))->configure();
    }

    public function getType(): string
    {
        return 'text';
    }

    public function badge(bool|Closure $condition = true): static
    {
        $this->isBadge = $condition;

        return $this;
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
     * An icon for a badge.
     */
    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * xs, sm, md or lg.
     */
    public function size(string|Closure|null $size): static
    {
        $this->size = $size;

        return $this;
    }

    /**
     * thin, light, normal, medium, semibold, bold or black.
     */
    public function weight(string|Closure|null $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    /**
     * sans, serif or mono.
     */
    public function fontFamily(string|Closure|null $family): static
    {
        $this->fontFamily = $family;

        return $this;
    }

    public function tooltip(string|Closure|null $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    protected function getProps(): array
    {
        $content = $this->evaluate($this->content);

        return [
            'content' => $content instanceof Htmlable ? null : $content,
            'html' => $content instanceof Htmlable ? $content->toHtml() : null,
            ...$this->evaluateConfigured([
                'badge' => $this->isBadge,
                'color' => $this->color,
                'icon' => $this->icon,
                'size' => $this->size,
                'weight' => $this->weight,
                'fontFamily' => $this->fontFamily,
                'tooltip' => $this->tooltip,
            ]),
        ];
    }
}
