<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Illuminate\Support\Str;

/**
 * @api
 */
class Tab extends Layout
{
    final public function __construct(protected string|Closure $label) {}

    public static function make(string|Closure $label): static
    {
        return (new static($label))->configure();
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
        return [...parent::getProps(), 'slug' => $this->getSlug()];
    }
}
