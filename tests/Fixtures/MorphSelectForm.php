<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Form;

class MorphSelectForm extends Form
{
    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [Select::make('owner_id')->relationship('owner', 'name')];
    }
}
