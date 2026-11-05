<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

class NestedRowsForm extends Form
{
    public function schema(): array
    {
        return [
            Repeater::make('sections')->schema([
                Repeater::make('lines')->schema([
                    TextInput::make('qty')->numeric(),
                ]),
            ]),
        ];
    }
}
