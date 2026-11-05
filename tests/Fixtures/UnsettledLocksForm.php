<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\State\Get;

/**
 * Conditions that keep flipping each other, so the locks never settle.
 */
class UnsettledLocksForm extends Form
{
    public function schema(): array
    {
        return [
            TextInput::make('first')->hidden(fn (Get $get): bool => filled($get('second'))),
            TextInput::make('second')->hidden(fn (Get $get): bool => blank($get('first'))),
            TextInput::make('third')->hidden(fn (Get $get): bool => blank($get('first')) && blank($get('second'))),
        ];
    }
}
