<?php

use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Components\Toggle;
use Xelqun\Forms\Conditions\When;
use Xelqun\Forms\Form;
use Xelqun\Forms\Tests\Fixtures\Country;
use Xelqun\Forms\Tests\Fixtures\OrderForm;
use Xelqun\Forms\Tests\Fixtures\RowVisibilityForm;

beforeEach(function () {
    $this->country = Country::create(['name' => 'Philippines']);
});

function orderData(array $overrides = []): array
{
    return array_replace_recursive([
        'customer' => 'Ada',
        'type' => 'individual',
        'company' => 'Should be dropped',
        'country_id' => null,
        'region_id' => null,
        'items' => [
            'row-a' => ['product' => 'Desk', 'qty' => 2, 'price' => 10.5, 'subtotal' => 999, 'attachment' => null],
            'row-b' => ['product' => 'Lamp', 'qty' => '3', 'price' => '4', 'subtotal' => 0, 'attachment' => null],
        ],
        'total' => 1,
    ], $overrides);
}

it('drops values of fields hidden by conditions', function () {
    $data = OrderForm::make()->validate(['data' => orderData()]);

    expect($data)->not->toHaveKey('company');
});

it('keeps values of fields shown by conditions', function () {
    $data = OrderForm::make()->validate(['data' => orderData(['type' => 'business', 'company' => 'Acme'])]);

    expect($data['company'])->toBe('Acme');
});

it('evaluates a visible() closure against its own row', function () {
    $data = RowVisibilityForm::make()->validate(['items' => [
        'a' => ['kind' => 'custom', 'note' => 'Kept'],
        'b' => ['kind' => 'plain', 'note' => 'Dropped'],
    ]]);

    expect($data['items'][0]['note'])->toBe('Kept')
        ->and($data['items'][1])->not->toHaveKey('note');
});

it('leaves a field the user switched off out of computed values', function () {
    $form = new class extends Form
    {
        public function schema(): array
        {
            return [
                Toggle::make('has_fee'),
                TextInput::make('fee')->numeric()->default(50)->visibleWhen(When::eq('has_fee', true)),
                TextInput::make('total')->numeric()->computed('fee + 1')->disabled(),
            ];
        }
    };

    expect($form::make()->validate(['has_fee' => false, 'fee' => 9])['total'])->toBe(1);
});

it('recomputes computed values instead of trusting the client', function () {
    $data = OrderForm::make()->validate(['data' => orderData()]);

    expect($data['items'][0]['subtotal'])->toBe(21)
        ->and($data['items'][1]['subtotal'])->toBe(12)
        ->and($data['total'])->toBe(33);
});

it('returns repeater rows as plain lists', function () {
    $data = OrderForm::make()->validate(['data' => orderData()]);

    expect(array_keys($data['items']))->toBe([0, 1])
        ->and($data['items'][0]['product'])->toBe('Desk');
});

it('applies requiredWhen only while its condition is true', function () {
    try {
        OrderForm::make()->validate(['data' => orderData(['type' => 'business', 'company' => null])]);
        $this->fail('Validation should fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('data.company');
    }
});

it('keys nested errors by row and labels them with the row number', function () {
    try {
        OrderForm::make()->validate(['data' => orderData(['items' => ['row-b' => ['price' => null]]])]);
        $this->fail('Validation should fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'data.items.row-b.price' => ['The Price (Line 2) field is required.'],
        ]);
    }
});

it('rejects select values that are not options', function () {
    try {
        OrderForm::make()->validate(['data' => orderData(['type' => 'pirate'])]);
        $this->fail('Validation should fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('data.type');
    }
});

it('rejects relationship values that do not exist', function () {
    try {
        OrderForm::make()->validate(['data' => orderData(['country_id' => 999])]);
        $this->fail('Validation should fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('data.country_id');
    }

    expect(OrderForm::make()->validate(['data' => orderData(['country_id' => $this->country->id])])['country_id'])
        ->toBe($this->country->id);
});

it('never returns keys that are not in the schema', function () {
    $data = OrderForm::make()->validate(['data' => orderData([
        'is_admin' => true,
        'items' => ['row-a' => ['injected' => 'nope']],
    ])]);

    expect($data)->not->toHaveKey('is_admin')
        ->and($data['items'][0])->not->toHaveKey('injected')
        ->and(array_keys($data['items'][0]))->toBe(['product', 'qty', 'price', 'subtotal', 'attachment']);
});
