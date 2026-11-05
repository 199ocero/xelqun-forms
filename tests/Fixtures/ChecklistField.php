<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Repeater;

/**
 * A row field from a plugin. Everything row-related comes from the contract.
 */
class ChecklistField extends Repeater
{
    public function getType(): string
    {
        return 'acme/checklist';
    }
}
