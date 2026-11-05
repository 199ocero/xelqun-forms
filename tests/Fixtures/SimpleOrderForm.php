<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

class SimpleOrderForm extends Form
{
    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [TextInput::make('customer')];
    }
}
