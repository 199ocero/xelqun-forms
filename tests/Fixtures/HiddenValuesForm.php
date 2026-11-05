<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Section;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\State\Set;

/**
 * Server values the browser must never receive, read by closures that need them.
 */
class HiddenValuesForm extends Form
{
    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [
            TextInput::make('customer')->afterStateUpdated(fn (Set $set) => $set('company', 'Written by a hook')),
            TextInput::make('country_id')->hidden(),
            Section::make('Admin')->hidden()->schema([TextInput::make('total')]),
            Select::make('region_id')->searchable()->getSearchResultsUsing(
                fn (Get $get): array => Region::query()->where('country_id', $get('country_id'))->pluck('name', 'id')->all(),
            ),
            TextInput::make('type'),
            TextInput::make('company')->hidden(fn (Get $get): bool => $get('type') !== 'business'),
            Section::make('Billing')->hidden(fn (Get $get): bool => $get('type') !== 'business')->schema([
                TextInput::make('region_note')->default('From the server'),
            ]),
            Repeater::make('notes')->schema([
                TextInput::make('body'),
                TextInput::make('secret')->default('Row secret')->hidden(),
            ]),
        ];
    }
}
