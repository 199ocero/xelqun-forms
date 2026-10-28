<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Radio;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Conditions\When;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Section;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\State\Set;

class OrderForm extends Form
{
    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [
            TextInput::make('customer')->required(),
            Radio::make('type')->options(['individual' => 'Individual', 'business' => 'Business'])->default('individual'),
            Section::make('Business')->visibleWhen(When::eq('type', 'business'))->schema([
                TextInput::make('company')->requiredWhen(When::eq('type', 'business')),
            ]),
            Select::make('country_id')->relationship('country', 'name')
                ->afterStateUpdated(fn (Set $set) => $set('region_id', null)),
            Select::make('region_id')->label('Region')
                ->options(fn (Get $get): array => Region::query()->where('country_id', $get('country_id'))->pluck('name', 'id')->all()),
            Repeater::make('items')->relationship(orderColumn: 'sort')->itemLabel('Line')->schema([
                TextInput::make('product')->required(),
                TextInput::make('qty')->numeric()->minValue(1)->default(1),
                TextInput::make('price')->numeric()->required(),
                TextInput::make('subtotal')->numeric()->computed('qty * price')->disabled(),
                FileUpload::make('attachment')->disk('public')->directory('items'),
            ]),
            TextInput::make('total')->numeric()->computed('sum(items.*.subtotal)')->disabled(),
        ];
    }
}
