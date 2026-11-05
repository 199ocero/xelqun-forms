<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

class LockedValuesForm extends Form
{
    public function schema(): array
    {
        return [
            TextInput::make('price')->numeric()->default(100)->disabled(),
            TextInput::make('fee')->numeric()->default(50)->hidden(),
            TextInput::make('quantity')->numeric()->default(2),
            TextInput::make('total')->numeric()->computed('price * quantity + fee')->disabled(),
        ];
    }
}
