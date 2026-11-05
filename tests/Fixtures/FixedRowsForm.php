<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

class FixedRowsForm extends Form
{
    public function schema(): array
    {
        return [
            Repeater::make('items')->orderable(false)
                ->default([['sku' => 'A-1', 'qty' => 1], ['sku' => 'B-2', 'qty' => 2]])
                ->schema([
                    TextInput::make('sku')->disabled()->dehydrated(),
                    TextInput::make('qty')->numeric(),
                ]),
        ];
    }
}
