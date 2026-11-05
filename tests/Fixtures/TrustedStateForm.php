<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\State\Get;

/**
 * Closures that decide on state the browser must not control. The fields that
 * read "role" and "total" come first, so they run before those are locked.
 */
class TrustedStateForm extends Form
{
    public function schema(): array
    {
        return [
            TextInput::make('admin_note')->hidden(fn (Get $get): bool => $get('role') !== 'admin'),
            TextInput::make('member_note')->hidden(fn (Get $get): bool => $get('role') === 'admin'),
            TextInput::make('large_order_note')->hidden(fn (Get $get): bool => $get('total') < 1000),
            Select::make('plan')->searchable()
                ->getSearchResultsUsing(fn (Get $get): array => [$get('role') => 'Plans for '.$get('role')]),
            Select::make('channel')
                ->options(fn (Get $get): array => filled($get('internal')) ? ['leak' => 'Leak'] : ['safe' => 'Safe']),
            TextInput::make('role')->default('member')->disabled(),
            TextInput::make('internal')->hidden(),
            TextInput::make('quantity')->numeric()->default(1),
            TextInput::make('total')->numeric()->computed('quantity * 10')->disabled(),
        ];
    }
}
