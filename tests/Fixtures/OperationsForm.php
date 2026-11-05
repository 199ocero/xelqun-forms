<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\State\Set;

/**
 * @extends Form<Order>
 */
class OperationsForm extends Form
{
    /** @var array<int, mixed> */
    public static array $reads = [];

    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [
            TextInput::make('customer')
                ->required()
                ->disabled(function (Get $get): bool {
                    array_push(self::$reads, $get('company'), $get('type'), $get('total'));

                    return false;
                })
                ->formatStateUsing(fn (?string $state): string => strtoupper((string) $state))
                ->dehydrateStateUsing(fn (string $state): string => strtolower($state)),
            TextInput::make('type')->default('individual')->disabledOn('edit'),
            TextInput::make('company')->default('Acme')->hiddenOn('edit'),
            TextInput::make('total')->numeric()->default(7)->readOnly()->rules(['max:1']),
            TextInput::make('confirmation')->visibleOn('create')->saved(false),
            TextInput::make('reference')
                ->readOnly()
                ->saved(false)
                ->afterStateUpdated(fn (Set $set) => $set('customer', 'hooked')),
            TextInput::make('operation_label')
                ->saved(false)
                ->formatStateUsing(fn (string $operation): string => "on {$operation}"),
            Repeater::make('items')->relationship()->schema([
                TextInput::make('product')->formatStateUsing(fn (?string $state): string => strtoupper((string) $state)),
                TextInput::make('qty')->numeric()->default(1),
                TextInput::make('price')->numeric()->default(2),
                TextInput::make('subtotal')->numeric()->default(3)->readOnly(),
                TextInput::make('attachment')->readOnly()->formatStateUsing(fn (?string $state): ?string => $state === null ? null : strtoupper($state)),
            ]),
        ];
    }
}
