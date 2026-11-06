<?php

use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\Radio;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\ToggleButtons;
use Xelqun\Forms\Form;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\Tests\Fixtures\Order;
use Xelqun\Forms\Tests\Fixtures\OrderForm;
use Xelqun\Forms\Tests\Fixtures\Plan;
use Xelqun\Forms\Tests\Fixtures\Tag;

/**
 * @param  array<string, mixed>  $data
 * @return array<string, array<int, string>>
 */
function optionErrors(Form $form, array $data): array
{
    try {
        $form->validate($data);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

/**
 * @return array<string, mixed>
 */
function selectProps(Select $select): array
{
    return (array) Form::make([$select])->toSchema()['components'][0]['props'];
}

$grouped = ['In process' => ['draft' => 'Draft', 'review' => 'Review'], 'Done' => ['live' => 'Live'], 'none' => 'None'];

describe('groups', function () use ($grouped) {
    it('sends each option with its group, and leaves plain options alone', function () use ($grouped) {
        expect(selectProps(Select::make('status')->options($grouped))['options'])->toBe([
            ['value' => 'draft', 'label' => 'Draft', 'group' => 'In process'],
            ['value' => 'review', 'label' => 'Review', 'group' => 'In process'],
            ['value' => 'live', 'label' => 'Live', 'group' => 'Done'],
            ['value' => 'none', 'label' => 'None'],
        ]);
    });

    it('accepts an option inside a group and refuses the group label', function () use ($grouped) {
        $make = fn (): Form => Form::make([Select::make('status')->options($grouped)]);

        expect(optionErrors($make(), ['status' => 'live']))->toBe([])
            ->and(optionErrors($make(), ['status' => 'none']))->toBe([])
            ->and(optionErrors($make(), ['status' => 'Done']))->toHaveKey('data.status')
            ->and(optionErrors($make(), ['status' => 'nope']))->toHaveKey('data.status');
    });

    it('checks every value of a multiple select', function () use ($grouped) {
        $make = fn (): Form => Form::make([Select::make('status')->multiple()->options($grouped)]);

        expect(optionErrors($make(), ['status' => ['draft', 'live']]))->toBe([])
            ->and(optionErrors($make(), ['status' => ['draft', 'Done']]))->toHaveKey('data.status');
    });

    it('flattens them for a search', function () use ($grouped) {
        $select = Select::make('status')->searchable()->options($grouped);
        Form::make([$select]);

        expect($select->getOptions())->toBe(['draft' => 'Draft', 'review' => 'Review', 'live' => 'Live', 'none' => 'None']);
    });
});

describe('disableOptionWhen', function () {
    $publishedOff = fn (mixed $value): bool => $value === 'live';
    $options = ['draft' => 'Draft', 'live' => 'Live'];

    it('marks the option for the browser', function () use ($options, $publishedOff) {
        expect(selectProps(Select::make('status')->options($options)->disableOptionWhen($publishedOff))['options'])->toBe([
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'live', 'label' => 'Live', 'disabled' => true],
        ]);
    });

    it('receives the label and can read state', function () {
        $form = Form::make([
            Select::make('plan')->options(['a' => 'A']),
            Select::make('status')
                ->options(['x' => 'Banned'])
                ->disableOptionWhen(fn (string $label, Get $get): bool => $label === 'Banned' && $get('plan') === null),
        ]);

        expect($form->toSchema()['components'][1]['props']->options[0])->toHaveKey('disabled', true);
    });

    it('is refused on the server, whatever the field', function (Closure $field, array $data) {
        expect(optionErrors(Form::make([$field()]), $data))->toHaveKey('data.status');
    })->with([
        'select' => [fn () => Select::make('status')->options(['draft' => 'D', 'live' => 'L'])->disableOptionWhen(fn (mixed $value): bool => $value === 'live'), ['status' => 'live']],
        'multiple' => [fn () => Select::make('status')->multiple()->options(['draft' => 'D', 'live' => 'L'])->disableOptionWhen(fn (mixed $value): bool => $value === 'live'), ['status' => ['draft', 'live']]],
        'radio' => [fn () => Radio::make('status')->options(['draft' => 'D', 'live' => 'L'])->disableOptionWhen(fn (mixed $value): bool => $value === 'live'), ['status' => 'live']],
    ]);

    it('is refused on an async select and a relationship', function () {
        $async = Form::make([Select::make('status')->getSearchResultsUsing(fn (): array => [])->getOptionLabelUsing(fn (string $value): string => $value)->disableOptionWhen(fn (mixed $value): bool => $value === 'live')]);

        expect(optionErrors($async, ['status' => 'live']))->toHaveKey('data.status');

        $tag = Tag::query()->create(['name' => 'locked']);
        $form = new class extends OrderForm
        {
            public function schema(): array
            {
                return [Select::make('tags')->multiple()->relationship(titleAttribute: 'name')->disableOptionWhen(fn (string $label): bool => $label === 'locked')];
            }
        };

        expect(optionErrors($form::make(), ['tags' => [$tag->id]]))->toHaveKey('data.tags');
    });

    it('keeps a value the record already has', function () {
        $form = new class extends OrderForm
        {
            public function schema(): array
            {
                return [Select::make('type')->options(['individual' => 'I', 'business' => 'B'])->disableOptionWhen(fn (mixed $value): bool => true)];
            }
        };
        $order = Order::query()->create(['customer' => 'Ada', 'type' => 'business']);

        expect(optionErrors($form::make($order), ['type' => 'business']))->toBe([])
            ->and(optionErrors($form::make($order), ['type' => 'individual']))->toHaveKey('data.type');
    });
});

describe('boolean', function () {
    it('offers yes and no, with custom labels and placeholder', function () {
        $form = Form::make([Select::make('agreed')->boolean('Absolutely', 'Not at all', 'Make your mind up')]);
        $node = $form->toSchema()['components'][0];

        expect($node['props']->options)->toBe([['value' => 1, 'label' => 'Absolutely'], ['value' => 0, 'label' => 'Not at all']])
            ->and($node['placeholder'])->toBe('Make your mind up');
    });

    it('fills as a number and saves a boolean', function () {
        $form = new class extends OrderForm
        {
            public function schema(): array
            {
                return [Select::make('customer')->boolean()];
            }
        };
        $record = new Order;
        $record->exists = true;
        $record->customer = true;

        expect($form::make($record)->getData()['customer'])->toBe(1)
            ->and($form::make()->validate(['customer' => 0])['customer'])->toBeFalse()
            ->and($form::make()->validate(['customer' => '1'])['customer'])->toBeTrue()
            ->and($form::make()->validate(['customer' => null])['customer'])->toBeNull()
            ->and(optionErrors($form::make(), ['customer' => 2]))->toHaveKey('data.customer');
    });
});

it('turns a boolean default into its option, on every field that has them', function (Closure $field) {
    foreach ([[true, 1, true], [false, 0, false]] as [$default, $state, $saved]) {
        $form = Form::make([$field()->default($default)]);

        expect($form->getData()['v'])->toBe($state)
            ->and(Form::make([$field()->default($default)])->validate(['v' => $form->getData()['v']]))->toBe(['v' => $saved]);
    }
})->with([
    'select' => [fn () => Select::make('v')->boolean()],
    'toggle buttons' => [fn () => ToggleButtons::make('v')->boolean()],
    'radio' => [fn () => Radio::make('v')->boolean()],
]);

it('turns an enum default into its value, in a row too', function () {
    $field = fn (): Radio => Radio::make('plan')->options(Plan::class)->default(Plan::Pro);
    $form = Form::make([Repeater::make('rows')->default([['name' => 'x']])->schema([$field()])]);

    expect(array_values($form->getData()['rows'])[0]['plan'])->toBe('pro')
        ->and(Form::make([$field()])->getData()['plan'])->toBe('pro');
});

it('leaves a boolean field empty with no default', function () {
    expect(Form::make([Select::make('v')->boolean()])->getData()['v'])->toBeNull();
});

it('sends messages and reorderable only when used', function () {
    expect(selectProps(Select::make('tag')->options(['a' => 'A'])))->not->toHaveKeys(['messages', 'reorderable']);

    $props = selectProps(Select::make('tag')
        ->multiple()
        ->searchable()
        ->options(['a' => 'A'])
        ->reorderable()
        ->loadingMessage('Hold on')
        ->noOptionsMessage('Nothing here')
        ->noSearchResultsMessage('No match')
        ->searchPrompt('Start typing')
        ->searchingMessage(fn (): string => 'Looking'));

    expect($props['reorderable'])->toBeTrue()
        ->and($props['messages'])->toBe([
            'loading' => 'Hold on',
            'noOptions' => 'Nothing here',
            'noResults' => 'No match',
            'prompt' => 'Start typing',
            'searching' => 'Looking',
        ]);
});
