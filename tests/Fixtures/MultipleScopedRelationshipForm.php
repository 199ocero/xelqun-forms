<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Form;

class MultipleScopedRelationshipForm extends Form
{
    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [
            Select::make('country_id')->multiple()->relationship('country', 'name', fn (Builder $query) => $query->where('name', 'Allowed')),
        ];
    }
}
