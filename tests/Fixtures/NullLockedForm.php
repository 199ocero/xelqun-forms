<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

class NullLockedForm extends Form
{
    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [
            TextInput::make('customer'),
            TextInput::make('company')->default('Acme')->disabled()->dehydrated(),
        ];
    }
}
