<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

class RowTotalsForm extends Form
{
    public function schema(): array
    {
        return [
            Repeater::make('items')->schema([
                TextInput::make('qty')->numeric(),
                TextInput::make('price')->numeric(),
                TextInput::make('subtotal')->numeric()->computed('qty * price')->disabled(),
            ]),
            TextInput::make('total')->numeric()->computed('sum(items.*.subtotal)')->disabled(),
        ];
    }
}
