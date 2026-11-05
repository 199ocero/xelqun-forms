<?php

namespace Xelqun\Forms\Components;

/**
 * @api
 */
class Toggle extends Checkbox
{
    public function getType(): string
    {
        return 'toggle';
    }
}
