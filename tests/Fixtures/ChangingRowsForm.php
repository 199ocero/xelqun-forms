<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

/**
 * Rows stored on the record rather than in a relationship, so another user
 * can change them between two requests.
 */
class ChangingRowsForm extends Form
{
    public function schema(): array
    {
        return [
            Repeater::make('rows')->schema([
                TextInput::make('sku')->disabled()->dehydrated(),
                TextInput::make('qty')->numeric(),
            ]),
        ];
    }
}
