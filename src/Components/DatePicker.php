<?php

namespace Xelqun\Forms\Components;

/**
 * @api
 */
class DatePicker extends DateTimePicker
{
    protected bool $hasTime = false;

    public function getType(): string
    {
        return 'date-picker';
    }
}
