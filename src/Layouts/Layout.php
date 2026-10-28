<?php

namespace Xelqun\Forms\Layouts;

use Xelqun\Forms\Components\Component;

/**
 * Base class for components that only arrange other components.
 */
abstract class Layout extends Component
{
    /** @var array<string, int>|null */
    protected ?array $columns = null;

    /**
     * Number of grid columns. An integer means one column on small screens
     * and that many from the "md" breakpoint.
     *
     * @param  int|array<string, int>  $columns
     */
    public function columns(int|array $columns): static
    {
        $this->columns = is_int($columns)
            ? ($columns > 1 ? ['default' => 1, 'md' => $columns] : ['default' => 1])
            : $columns;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getProps(): array
    {
        return ['columns' => $this->columns];
    }
}
