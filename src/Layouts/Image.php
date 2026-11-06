<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Xelqun\Forms\Components\Component;

/**
 * @api
 */
class Image extends Component
{
    protected int|string|Closure|null $width = null;

    protected int|string|Closure|null $height = null;

    protected string|Closure|null $alignment = null;

    protected string|Closure|null $tooltip = null;

    final public function __construct(protected string|Closure $url, protected string|Closure $alt) {}

    public static function make(string|Closure $url, string|Closure $alt): static
    {
        return (new static($url, $alt))->configure();
    }

    public function getType(): string
    {
        return 'image';
    }

    /**
     * Pixels as a number, or any CSS size as a string.
     */
    public function imageWidth(int|string|Closure|null $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function imageHeight(int|string|Closure|null $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function imageSize(int|string|Closure|null $size): static
    {
        return $this->imageWidth($size)->imageHeight($size);
    }

    /**
     * start, center or end.
     */
    public function alignment(string|Closure|null $alignment): static
    {
        $this->alignment = $alignment;

        return $this;
    }

    public function alignStart(): static
    {
        return $this->alignment('start');
    }

    public function alignCenter(): static
    {
        return $this->alignment('center');
    }

    public function alignEnd(): static
    {
        return $this->alignment('end');
    }

    public function tooltip(string|Closure|null $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    protected function getProps(): array
    {
        return [
            'url' => $this->evaluate($this->url),
            'alt' => $this->evaluate($this->alt),
            ...$this->evaluateConfigured([
                'width' => $this->width,
                'height' => $this->height,
                'alignment' => $this->alignment,
                'tooltip' => $this->tooltip,
            ]),
        ];
    }
}
