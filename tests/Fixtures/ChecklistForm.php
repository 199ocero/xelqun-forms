<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Acme\Reviews\Receipt;
use Acme\Reviews\Reviewer;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

class ChecklistForm extends Form
{
    public function schema(): array
    {
        return [
            ChecklistField::make('steps')->defaultItems(1)->schema([
                TextInput::make('title')->required(),
                Reviewer::make('owner')->options(['ada' => 'Ada Lovelace']),
                Receipt::make('proof'),
            ]),
        ];
    }
}
