<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

class PasswordRowsForm extends Form
{
    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [Repeater::make('meta')->schema([
            TextInput::make('host'),
            TextInput::make('secret')->password(),
        ])];
    }
}
