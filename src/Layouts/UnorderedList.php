<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Xelqun\Forms\Components\Component;

/**
 * A bulleted list. Each item is a component, or a string for plain text.
 *
 * @api
 */
class UnorderedList extends Layout
{
    protected string|Closure|null $size = null;

    /**
     * @param  array<int, Component|string>  $items
     */
    final public function __construct(array $items = [])
    {
        $this->schema(array_map(
            fn (Component|string $item): Component => $item instanceof Component ? $item : Text::make($item),
            $items,
        ));
    }

    /**
     * @param  array<int, Component|string>  $items
     */
    public static function make(array $items = []): static
    {
        return (new static($items))->configure();
    }

    public function getType(): string
    {
        return 'unordered-list';
    }

    /**
     * xs, sm, md or lg.
     */
    public function size(string|Closure|null $size): static
    {
        $this->size = $size;

        return $this;
    }

    protected function getProps(): array
    {
        return $this->evaluateConfigured(['size' => $this->size]);
    }
}
