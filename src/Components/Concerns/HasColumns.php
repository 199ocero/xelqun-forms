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
        $this->columns = $this->normalizeColumns($columns);

        return $this;
    }

    /**
     * @param  int|array<string, int>  $columns
     * @return array<string, int>
     */
    protected function normalizeColumns(int|array $columns): array
    {
        if (is_array($columns)) {
            return $columns;
        }

        return $columns > 1 ? ['default' => 1, 'md' => $columns] : ['default' => 1];
    }
}
