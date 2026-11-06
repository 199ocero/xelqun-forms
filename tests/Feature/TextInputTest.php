<?php

use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\Tests\Fixtures\Order;
use Xelqun\Forms\Tests\Fixtures\PasswordForm;
use Xelqun\Forms\Tests\Fixtures\PasswordRowsForm;

/**
 * @return array<string, mixed>
 */
function inputProps(TextInput $input): array
{
    return (array) Form::make([$input])->toSchema()['components'][0]['props'];
}

it('trims before validating, so spaces alone are empty', function () {
    expect(Form::make([TextInput::make('name')->trim()])->validate(['name' => '  Ada  ']))->toBe(['name' => 'Ada'])
        ->and(fn () => Form::make([TextInput::make('name')->trim()->required()])->validate(['name' => '   ']))->toThrow(ValidationException::class);
});

it('keeps spaces without trim', function () {
    expect(Form::make([TextInput::make('name')])->validate(['name' => ' Ada ']))->toBe(['name' => ' Ada ']);
});

it('strips characters before validating and casting', function () {
    $form = Form::make([TextInput::make('amount')->numeric()->stripCharacters(',')->maxValue(2000)]);

    expect($form->validate(['amount' => '1,000']))->toBe(['amount' => 1000])
        ->and(fn () => Form::make([TextInput::make('amount')->numeric()->stripCharacters(',')->maxValue(2000)])->validate(['amount' => '3,000']))->toThrow(ValidationException::class);
});

it('strips a list of characters', function () {
    expect(Form::make([TextInput::make('phone')->stripCharacters(['(', ')', '-'])])->validate(['phone' => '(02)-123']))->toBe(['phone' => '02123']);
});

it('lets computed values see the stripped value', function () {
    $form = Form::make([
        TextInput::make('amount')->stripCharacters(','),
        TextInput::make('double')->numeric()->computed('amount * 2'),
    ]);

    expect($form->validate(['amount' => '1,000'])['double'])->toBe(2000);
});

it('leaves a locked value alone', function () {
    $form = Form::make([TextInput::make('code')->default(' a,b ')->trim()->stripCharacters(',')->disabled()->saved()]);

    expect($form->validate(['code' => 'x']))->toBe(['code' => ' a,b ']);
});

it('leaves a value that is not text to the rules', function () {
    expect(fn () => Form::make([TextInput::make('name')->trim()->stripCharacters(',')])->validate(['name' => ['a']]))->toThrow(ValidationException::class);
});

it('requires an exact length, and caps the input', function () {
    $make = fn (): Form => Form::make([TextInput::make('code')->length(4)]);

    expect($make()->validate(['code' => 'abcd']))->toBe(['code' => 'abcd'])
        ->and(fn () => $make()->validate(['code' => 'abc']))->toThrow(ValidationException::class)
        ->and(inputProps(TextInput::make('code')->length(4))['maxLength'])->toBe(4);
});

it('sends its options only when they are used', function () {
    expect(inputProps(TextInput::make('name')))
        ->not->toHaveKeys(['revealable', 'mask', 'datalist', 'copyable', 'prefixIcon', 'suffixIcon']);
});

it('sends its options, resolving closures', function () {
    expect(inputProps(
        TextInput::make('name')
            ->password()
            ->revealable()
            ->mask(fn (): string => '99/99')
            ->datalist(['BMW', 'Ford'])
            ->copyable(copyMessage: 'Done', copyMessageDuration: 500)
            ->prefixIcon('mail')
            ->suffixIcon('check'),
    ))->toMatchArray([
        'revealable' => true,
        'mask' => '99/99',
        'datalist' => ['BMW', 'Ford'],
        'copyable' => ['message' => 'Done', 'duration' => 500],
        'prefixIcon' => 'mail',
        'suffixIcon' => 'check',
    ]);
});

it('tells the browser when a closure turns an option off', function () {
    $form = Form::make([
        TextInput::make('on'),
        TextInput::make('name')
            ->revealable(fn (Get $get): bool => $get('on') === 'yes')
            ->mask(fn (Get $get): ?string => $get('on') === 'yes' ? '99' : null)
            ->copyable(fn (Get $get): bool => $get('on') === 'yes'),
    ]);
    $props = (array) $form->toSchema()['components'][1]['props'];

    expect($props)->toHaveKey('revealable', false)
        ->toHaveKey('mask', null)
        ->toHaveKey('copyable', null);
});

describe('password()', function () {
    it('never sends the stored value to the browser', function () {
        $order = Order::create(['customer' => 'stored-hash']);

        $schema = json_encode(PasswordForm::make($order)->toSchema());

        expect($schema)->not->toContain('stored-hash');
    });

    it('keeps the stored value when left empty', function (?string $value) {
        $order = Order::create(['customer' => 'stored-hash']);

        PasswordForm::make($order)->save(['data' => ['customer' => $value]]);

        expect($order->fresh()->customer)->toBe('stored-hash');
    })->with(['null' => null, 'empty' => '']);

    it('keeps a stored value in a row that is written back whole', function () {
        $order = Order::create(['customer' => 'Ada', 'meta' => [['host' => 'smtp', 'secret' => 'stored']]]);
        $form = PasswordRowsForm::make($order);

        $form->save(['data' => $form->getData()]);

        expect($order->fresh()->meta[0]['secret'])->toBe('stored');
    });

    it('saves a new value', function () {
        $order = Order::create(['customer' => 'stored-hash']);

        PasswordForm::make($order)->save(['data' => ['customer' => 'new-hash']]);

        expect($order->fresh()->customer)->toBe('new-hash');
    });

    it('lets saved() insist on the empty value', function () {
        $form = Form::make([TextInput::make('code')->password()->saved()]);

        expect($form->validate(['code' => null]))->toBe(['code' => null]);
    });

    it('still checks the rules of an empty value', function () {
        $form = Form::make([TextInput::make('code')->password()->required()]);

        expect(fn () => $form->validate(['code' => '']))->toThrow(ValidationException::class);
    });
});
