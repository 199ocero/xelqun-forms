<?php

namespace Xelqun\Forms\Layouts;

/**
 * @api
 */
class Grid extends Layout
{
    /**
     * @param  int|array<string, int>  $columns
     */
    final public function __construct(int|array $columns = 2)
    {
        $this->columns($columns);
    }

    /**
     * @param  int|array<string, int>  $columns
     */
    public static function make(int|array $columns = 2): static
    {
        return (new static($columns))->configure();
    }

    public function getType(): string
    {
        return 'grid';
    }
}
