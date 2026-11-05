<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\State\Get;

class RowVisibilityForm extends Form
{
    public function schema(): array
    {
        return [
            Repeater::make('items')->schema([
                TextInput::make('kind'),
                TextInput::make('note')->visible(fn (Get $get): bool => $get('kind') === 'custom'),
            ]),
        ];
    }
}
