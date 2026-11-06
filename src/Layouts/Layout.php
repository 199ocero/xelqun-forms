<?php

namespace Xelqun\Forms\Layouts;

use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Concerns\HasColumns;

/**
 * Base class for components that only arrange other components.
 *
 * @api
 */
abstract class Layout extends Component
{
    use HasColumns;

    /**
     * @return array<string, mixed>
     */
    protected function getProps(): array
    {
        return ['columns' => $this->columns];
    }
}
