<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

class LockedRelationRowsForm extends Form
{
    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [
            TextInput::make('customer'),
            Repeater::make('items')->relationship()->schema([
                TextInput::make('product')->default('Unknown')->disabled()->dehydrated(),
                TextInput::make('qty')->numeric(),
                TextInput::make('price')->numeric(),
                TextInput::make('subtotal')->numeric(),
            ]),
        ];
    }
}
