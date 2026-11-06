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

    /**
     * The user can't reach this field to fix it, so only the server checks it.
     */
    public function getClientValidation(): ?array
    {
        return null;
    }
}
