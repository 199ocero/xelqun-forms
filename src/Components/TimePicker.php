<?php

namespace Xelqun\Forms\Components;

/**
 * @api
 */
class TimePicker extends DateTimePicker
{
    protected bool $hasDate = false;

    public function getType(): string
    {
        return 'time-picker';
    }
}
