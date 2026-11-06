<?php

namespace Xelqun\Forms\Components\Concerns;

/**
 * @api
 */
trait HasColumns
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
}
