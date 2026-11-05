<?php

namespace Xelqun\Forms\Components;

/**
 * @api
 */
class Hidden extends Field
{
    public function getType(): string
    {
        return 'hidden';
    }
}
