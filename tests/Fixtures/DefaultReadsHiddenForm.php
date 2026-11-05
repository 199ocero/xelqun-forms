<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\State\Get;

/**
 * Default closures that read a hidden field declared after them. Row fields
 * have no initial value in a new row, so their default runs on every request.
 */
class DefaultReadsHiddenForm extends Form
{
    public function schema(): array
    {
        return [
            TextInput::make('price')->disabled()->dehydrated()->default(fn (Get $get): mixed => $get('internal_price')),
            TextInput::make('internal_price')->hidden(),
            Repeater::make('items')->schema([
                TextInput::make('price')->disabled()->dehydrated()->default(fn (Get $get): mixed => $get('internal_price')),
                TextInput::make('internal_price')->hidden(),
            ]),
        ];
    }
}
