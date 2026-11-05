<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

/**
 * @extends Form<Order>
 */
class NestedRelationshipForm extends Form
{
    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [
            TextInput::make('customer'),
            Repeater::make('items')->relationship('items')->schema([
                TextInput::make('product'),
                TextInput::make('qty')->numeric(),
                TextInput::make('price')->numeric(),
                TextInput::make('subtotal')->numeric()->computed('qty * price'),
                Repeater::make('reviews')->relationship('reviews')->schema([TextInput::make('stars')]),
            ]),
        ];
    }
}
