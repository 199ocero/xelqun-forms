<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Xelqun\Forms\Components\MorphToSelect;
use Xelqun\Forms\Components\MorphToSelect\Type;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Group;
use Xelqun\Forms\Layouts\Section;
use Xelqun\Forms\State\Get;

/**
 * @extends Form<Order>
 */
class RelationsForm extends Form
{
    /** @var array<int, string> */
    public static array $calls = [];

    public static bool $hideRelations = false;

    public static ?string $failIn = null;

    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [
            TextInput::make('customer')->required(),
            Select::make('tags')
                ->multiple()
                ->relationship(titleAttribute: 'name', modifyQueryUsing: fn (Builder $query) => $query->where('group', 'public'))
                ->getOptionLabelFromRecordUsing(fn (Tag $record): string => "#{$record->name}")
                ->pivotData(fn (Order $record): array => ['added_by' => "order-{$record->id}"])
                ->hidden(fn (): bool => self::$hideRelations),
            MorphToSelect::make('owner')
                ->required(fn (): bool => $this->getContext()['requireOwner'] ?? false)
                ->types([
                    Type::make(Country::class)->titleAttribute('name'),
                    Type::make(Region::class)
                        ->titleAttribute('name')
                        ->label('Area')
                        ->getOptionLabelFromRecordUsing(fn (Region $record): string => strtoupper($record->name))
                        ->modifyOptionsQueryUsing(fn (Builder $query) => $query->where('name', '!=', 'Secret')),
                ]),
            Group::make([
                TextInput::make('body')->required(),
                TextInput::make('stamp')->default(fn (Get $get): mixed => $get('$.customer'))->readOnly(),
            ])->relationship('note')->hidden(fn (): bool => self::$hideRelations),
            Section::make('Country')->schema([
                Group::make([TextInput::make('name')])->relationship('country')->hidden(fn (): bool => self::$hideRelations),
            ]),
            Repeater::make('items')->relationship()->schema([
                TextInput::make('product'),
                TextInput::make('qty')->numeric()->default(1),
                TextInput::make('price')->numeric()->default(1),
                TextInput::make('subtotal')->numeric()->computed('qty * price'),
                Group::make([TextInput::make('stars')->numeric()])->relationship('review'),
            ]),
        ];
    }

    public function beforeSave(array $data): void
    {
        $this->record('beforeSave '.Order::query()->count());
    }

    public function mutateBeforeSave(array $attributes): array
    {
        $this->record('mutateBeforeSave');

        return $attributes;
    }

    public function afterSave(Model $record, array $data): void
    {
        $this->record('afterSave '.OrderNote::query()->count());
    }

    public function afterCommit(Model $record, array $data): void
    {
        $this->record('afterCommit '.$data['customer']);
    }

    private function record(string $call): void
    {
        self::$calls[] = $call;

        if (self::$failIn !== null && str_starts_with($call, self::$failIn)) {
            throw new RuntimeException("Failed in {$call}");
        }
    }
}
