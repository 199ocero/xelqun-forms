<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Xelqun\Forms\Components\Component;

/**
 * Children share a row by flexbox instead of the grid. Each grows to fill the
 * space, unless it says grow(false).
 *
 * @api
 */
class Flex extends Layout
{
    protected string|Closure|null $fromBreakpoint = null;

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
        return 'flex';
    }

    /**
     * Stack the children below this breakpoint: sm, md, lg, xl or 2xl.
     */
    public function from(string|Closure|null $breakpoint): static
    {
        $this->fromBreakpoint = $breakpoint;

        return $this;
    }

    protected function getProps(): array
    {
        return ['from' => $this->evaluate($this->fromBreakpoint)];
    }
}
