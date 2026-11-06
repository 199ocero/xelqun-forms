<?php

use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\CheckboxList;
use Xelqun\Forms\Components\ColorPicker;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Components\KeyValue;
use Xelqun\Forms\Components\Slider;
use Xelqun\Forms\Components\TagsInput;
use Xelqun\Forms\Components\ToggleButtons;
use Xelqun\Forms\Form;
use Xelqun\Forms\Tests\Fixtures\Order;
use Xelqun\Forms\Tests\Fixtures\OrderForm;

/**
 * @return array<string, array<int, string>>
 */
function fieldErrors(Field $field, mixed $value): array
{
    try {
        Form::make([$field])->validate([$field->getName() => $value]);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

function validated(Field $field, mixed $value): mixed
{
    return Form::make([$field])->validate([$field->getName() => $value])[$field->getName()] ?? 'missing';
}

/**
 * @return array<string, mixed>
 */
function nodeProps(Field $field): array
{
    return (array) Form::make([$field])->toSchema()['components'][0]['props'];
}

/**
 * A form holding one field, filled from a value the record already has.
 */
function storedForm(Closure $field, mixed $stored): Form
{
    $form = new class extends OrderForm
    {
        public static Closure $field;

        public function schema(): array
        {
            return [(self::$field)()];
        }
    };
    $form::$field = $field;
    $record = new Order;
    $record->exists = true;
    $record->setRawAttributes(['customer' => $stored]);

    return $form::make($record);
}

describe('CheckboxList', function () {
    $options = ['a' => 'A', 'b' => 'B', 'c' => 'C'];

    it('holds a list and checks every value', function () use ($options) {
        expect(validated(CheckboxList::make('v')->options($options), ['a', 'c']))->toBe(['a', 'c'])
            ->and(fieldErrors(CheckboxList::make('v')->options($options), ['a', 'x']))->toHaveKey('data.v')
            ->and(fieldErrors(CheckboxList::make('v')->options($options), 'a'))->toHaveKey('data.v');
    });

    it('starts empty, and required means at least one', function () use ($options) {
        expect(Form::make([CheckboxList::make('v')->options($options)])->getData()['v'])->toBe([])
            ->and(fieldErrors(CheckboxList::make('v')->options($options)->required(), []))->toHaveKey('data.v');
    });

    it('refuses a disabled option', function () use ($options) {
        $make = fn (): CheckboxList => CheckboxList::make('v')->options($options)->disableOptionWhen(fn (mixed $value): bool => $value === 'c');

        expect(fieldErrors($make(), ['a', 'c']))->toHaveKey('data.v')
            ->and(nodeProps($make())['options'][2])->toHaveKey('disabled', true);
    });

    it('sends descriptions and its display options', function () use ($options) {
        $props = nodeProps(CheckboxList::make('v')->options($options)->descriptions(['a' => 'First'])->columns(2)->searchable()->bulkToggleable());

        expect($props['options'][0])->toHaveKey('description', 'First')
            ->and($props['options'][1])->toHaveKey('description', null)
            ->and($props)->toMatchArray(['columns' => ['default' => 1, 'md' => 2], 'searchable' => true, 'bulkToggleable' => true]);
    });
});

describe('ToggleButtons', function () {
    $options = ['draft' => 'Draft', 'live' => 'Live'];

    it('holds one value, or a list when multiple', function () use ($options) {
        expect(validated(ToggleButtons::make('v')->options($options), 'live'))->toBe('live')
            ->and(fieldErrors(ToggleButtons::make('v')->options($options), 'x'))->toHaveKey('data.v')
            ->and(validated(ToggleButtons::make('v')->multiple()->options($options), ['draft', 'live']))->toBe(['draft', 'live'])
            ->and(fieldErrors(ToggleButtons::make('v')->multiple()->options($options), ['x']))->toHaveKey('data.v')
            ->and(Form::make([ToggleButtons::make('v')->multiple()->options($options)])->getData()['v'])->toBe([]);
    });

    it('refuses a disabled option', function () use ($options) {
        expect(fieldErrors(ToggleButtons::make('v')->options($options)->disableOptionWhen(fn (mixed $value): bool => $value === 'live'), 'live'))->toHaveKey('data.v');
    });

    it('adds colors and icons to each option', function () use ($options) {
        expect(nodeProps(ToggleButtons::make('v')->options($options)->colors(['live' => 'success'])->icons(['live' => 'check']))['options'])->toBe([
            ['value' => 'draft', 'label' => 'Draft', 'color' => null, 'icon' => null],
            ['value' => 'live', 'label' => 'Live', 'color' => 'success', 'icon' => 'check'],
        ]);
    });

    it('offers yes and no, saved as a boolean', function () {
        $make = fn (): ToggleButtons => ToggleButtons::make('v')->boolean('Absolutely');

        expect(nodeProps($make())['options'])->toBe([
            ['value' => 1, 'label' => 'Absolutely', 'color' => 'success', 'icon' => 'check'],
            ['value' => 0, 'label' => 'No', 'color' => 'danger', 'icon' => 'x'],
        ])
            ->and(validated($make(), 1))->toBeTrue()
            ->and(validated($make(), 0))->toBeFalse();
    });

    it('lets colors override the boolean defaults', function () {
        expect(nodeProps(ToggleButtons::make('v')->colors([1 => 'info'])->boolean())['options'][0]['color'])->toBe('info');
    });
});

describe('TagsInput', function () {
    it('holds a list of strings', function () {
        expect(validated(TagsInput::make('v'), ['a', 'b']))->toBe(['a', 'b'])
            ->and(fieldErrors(TagsInput::make('v'), 'a'))->toHaveKey('data.v')
            ->and(fieldErrors(TagsInput::make('v'), [['nested']]))->toHaveKey('data.v');
    });

    it('cleans up spaces, blanks and duplicates', function () {
        expect(validated(TagsInput::make('v'), [' a ', '', 'a', 'b', null]))->toBe(['a', 'b']);
    });

    it('stores and reads a separated string', function () {
        expect(validated(TagsInput::make('v')->separator(','), ['a', 'b']))->toBe('a,b')
            ->and(storedForm(fn () => TagsInput::make('customer')->separator(','), 'a,b')->getData()['customer'])->toBe(['a', 'b'])
            ->and(storedForm(fn () => TagsInput::make('customer')->separator(','), '')->getData()['customer'])->toBe([]);
    });

    it('checks each tag with its own rules', function () {
        $make = fn (): TagsInput => TagsInput::make('v')->nestedRecursiveRules(['min:3']);

        expect(validated($make(), ['abc']))->toBe(['abc'])
            ->and(fieldErrors($make(), ['abc', 'x']))->toHaveKey('data.v');
    });

    it('sends its options', function () {
        expect(nodeProps(TagsInput::make('v')->suggestions(fn (): array => ['a'])->splitKeys(['Tab', ','])->reorderable()->tagPrefix('#')->tagSuffix('%')))->toBe([
            'suggestions' => ['a'],
            'splitKeys' => ['Tab', ','],
            'reorderable' => true,
            'tagPrefix' => '#',
            'tagSuffix' => '%',
        ]);
    });
});

describe('Slider', function () {
    it('starts at the minimum and is never empty', function () {
        expect(Form::make([Slider::make('v')->range(10, 20)])->getData()['v'])->toBe(10)
            ->and(validated(Slider::make('v')->range(10, 20), null))->toBe(10);
    });

    it('falls back to the default on a record with no value', function () {
        $range = storedForm(fn () => Slider::make('customer')->default([20, 70]), null);
        $single = storedForm(fn () => Slider::make('customer')->range(10, 20), null);

        expect($range->getData()['customer'])->toBe([20, 70])
            ->and($single->getData()['customer'])->toBe(10);
    });

    it('keeps a stored value, including zero', function () {
        expect(storedForm(fn () => Slider::make('customer')->default([20, 70]), [10, 30])->getData()['customer'])->toBe([10, 30])
            ->and(storedForm(fn () => Slider::make('customer')->range(0, 10)->default(5), 0)->getData()['customer'])->toBe(0);
    });

    it('stays on the track', function (mixed $value, bool $isValid) {
        $errors = fieldErrors(Slider::make('v')->range(10, 20)->step(5), $value);

        expect($errors === [])->toBe($isValid);
    })->with([
        'minimum' => [10, true],
        'maximum' => [20, true],
        'a step' => [15, true],
        'a numeric string' => ['15', true],
        'between steps' => [12, false],
        'below' => [5, false],
        'above' => [25, false],
        'text' => ['abc', false],
        'a list' => [[15], false],
    ]);

    it('counts steps from the minimum', function () {
        expect(fieldErrors(Slider::make('v')->range(1, 10)->step(2), 3))->toBe([])
            ->and(fieldErrors(Slider::make('v')->range(1, 10)->step(2), 4))->toHaveKey('data.v');
    });

    it('allows decimals without rounding errors', function () {
        expect(fieldErrors(Slider::make('v')->range(0, 1)->decimalPlaces(2), 0.07))->toBe([])
            ->and(fieldErrors(Slider::make('v')->range(0, 1)->decimalPlaces(1), 0.3))->toBe([])
            ->and(fieldErrors(Slider::make('v')->range(0, 1)->decimalPlaces(1), 0.35))->toHaveKey('data.v');
    });

    it('takes one value per handle', function () {
        $make = fn (): Slider => Slider::make('v')->default([20, 70]);

        expect(validated($make(), ['10', 30]))->toBe([10, 30])
            ->and(fieldErrors($make(), [10]))->toHaveKey('data.v')
            ->and(fieldErrors($make(), [10, 20, 30]))->toHaveKey('data.v')
            ->and(fieldErrors($make(), [10, 200]))->toHaveKey('data.v')
            ->and(fieldErrors($make(), 10))->toHaveKey('data.v');
    });

    it('casts to a number', function () {
        expect(validated(Slider::make('v'), '40'))->toBe(40);
    });
});

describe('KeyValue', function () {
    it('holds a flat map and drops rows with no key', function () {
        expect(validated(KeyValue::make('v'), ['a' => '1', '' => 'lost', ' ' => 'lost']))->toBe(['a' => '1'])
            ->and(Form::make([KeyValue::make('v')])->getData()['v'])->toBe([]);
    });

    it('refuses nested values', function () {
        expect(fieldErrors(KeyValue::make('v'), ['a' => ['b']]))->toHaveKey('data.v')
            ->and(fieldErrors(KeyValue::make('v'), 'a'))->toHaveKey('data.v');
    });

    it('enforces its limits on the server', function (Closure $configure, array $posted, bool $isValid) {
        $form = storedForm(fn () => $configure(KeyValue::make('customer')), ['a' => '1', 'b' => '2']);

        try {
            $form->validate(['customer' => $posted]);
            expect($isValid)->toBeTrue();
        } catch (ValidationException $exception) {
            expect($isValid)->toBeFalse()
                ->and($exception->errors())->toHaveKey('data.customer');
        }
    })->with([
        'addable off, a new key' => [fn (KeyValue $field) => $field->addable(false), ['a' => '1', 'b' => '2', 'c' => '3'], false],
        'addable off, a changed value' => [fn (KeyValue $field) => $field->addable(false), ['a' => '9', 'b' => '2'], true],
        'addable off, a row removed' => [fn (KeyValue $field) => $field->addable(false), ['a' => '1'], true],
        'deletable off, a row removed' => [fn (KeyValue $field) => $field->deletable(false), ['a' => '1'], false],
        'deletable off, a new key' => [fn (KeyValue $field) => $field->deletable(false), ['a' => '1', 'b' => '2', 'c' => '3'], true],
        'keys locked, a key renamed' => [fn (KeyValue $field) => $field->editableKeys(false), ['a' => '1', 'z' => '2'], false],
        'keys locked, a value changed' => [fn (KeyValue $field) => $field->editableKeys(false), ['a' => '9', 'b' => '2'], true],
        'values locked, a value changed' => [fn (KeyValue $field) => $field->editableValues(false), ['a' => '9', 'b' => '2'], false],
        'values locked, nothing changed' => [fn (KeyValue $field) => $field->editableValues(false), ['a' => '1', 'b' => '2'], true],
    ]);

    it('sends its labels and flags', function () {
        expect(nodeProps(KeyValue::make('v')->keyLabel('Name')->addActionLabel('More')->deletable(false)->reorderable()))->toMatchArray([
            'keyLabel' => 'Name',
            'valueLabel' => 'Value',
            'addLabel' => 'More',
            'addable' => true,
            'deletable' => false,
            'reorderable' => true,
        ]);
    });
});

describe('ColorPicker', function () {
    it('checks the format', function (Closure $configure, string $valid, string $invalid) {
        expect(fieldErrors($configure(ColorPicker::make('v')), $valid))->toBe([])
            ->and(fieldErrors($configure(ColorPicker::make('v')), $invalid))->toHaveKey('data.v');
    })->with([
        'hex' => [fn (ColorPicker $field) => $field, '#ff0000', 'red'],
        'short hex' => [fn (ColorPicker $field) => $field->hex(), '#f00', '#ff00'],
        'rgb' => [fn (ColorPicker $field) => $field->rgb(), 'rgb(1, 2, 3)', '#ff0000'],
        'rgba' => [fn (ColorPicker $field) => $field->rgba(), 'rgba(1, 2, 3, 0.5)', 'rgb(1, 2)'],
        'rgba at full opacity' => [fn (ColorPicker $field) => $field->rgba(), 'rgba(1, 2, 3, 1)', 'rgba(1, 2, 3, 2)'],
        'hsl' => [fn (ColorPicker $field) => $field->hsl(), 'hsl(120, 50%, 50%)', 'hsl(120, 50, 50)'],
    ]);

    it('refuses a value that is not text', function () {
        expect(fieldErrors(ColorPicker::make('v'), ['#fff']))->toHaveKey('data.v');
    });

    it('tells the browser the format', function () {
        expect(nodeProps(ColorPicker::make('v')->hsl()))->toBe(['format' => 'hsl']);
    });
});

it('saves a list field to a JSON column shape', function () {
    $form = Form::make([CheckboxList::make('perks')->options(['a' => 'A']), KeyValue::make('meta'), TagsInput::make('tags')]);

    expect($form->validate(['perks' => ['a'], 'meta' => ['k' => 'v'], 'tags' => ['t']]))
        ->toBe(['perks' => ['a'], 'meta' => ['k' => 'v'], 'tags' => ['t']]);
});
