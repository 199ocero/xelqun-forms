<?php

use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Components\Toggle;
use Xelqun\Forms\Conditions\When;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Section;
use Xelqun\Forms\Tests\Fixtures\Country;
use Xelqun\Forms\Tests\Fixtures\FixedRowsForm;
use Xelqun\Forms\Tests\Fixtures\NestedRowsForm;
use Xelqun\Forms\Tests\Fixtures\OrderForm;
use Xelqun\Forms\Tests\Fixtures\Region;

beforeEach(function () {
    $philippines = Country::create(['name' => 'Philippines']);
    Region::create(['country_id' => $philippines->id, 'name' => 'Cebu']);
});

it('serializes a versioned schema with state and nodes', function () {
    $schema = OrderForm::make()->toSchema();

    expect($schema['$xq'])->toBe(1)
        ->and($schema['id'])->toBe('order-form')
        ->and($schema['token'])->toBeString()
        ->and($schema['endpoints']['update'])->toBe('/_xelqun/forms/update')
        ->and(json_encode($schema['state']))->toBe('{"data":{"customer":null,"type":"individual","company":null,"country_id":null,"region_id":null,"items":{},"total":0}}');

    $keys = array_column($schema['components'], 'key');

    expect($keys)->toBe(['data.customer', 'data.type', 'data#2', 'data.country_id', 'data.region_id', 'data.items', 'data.total']);
});

it('gives each repeater level its own row placeholder', function () {
    $sections = json_decode(json_encode(NestedRowsForm::make()->toSchema()['components'][0]), true);
    $lines = $sections['props']['template'][0];

    expect($sections['props']['rowPlaceholder'])->toBe('{row}')
        ->and($lines['path'])->toBe('data.sections.{row}.lines')
        ->and($lines['props']['rowPlaceholder'])->toBe('{row:1}')
        ->and($lines['props']['template'][0]['path'])->toBe('data.sections.{row}.lines.{row:1}.qty');
});

it('refuses two fields that share a state path', function (array $schema) {
    $form = new class extends Form
    {
        /** @var array<int, mixed> */
        public static array $fields = [];

        public function schema(): array
        {
            return self::$fields;
        }
    };
    $form::$fields = $schema;

    expect(fn () => $form::make())->toThrow(FormDefinitionException::class, '[data.value]');
})->with([
    'side by side' => fn () => [TextInput::make('value'), Toggle::make('value')],
    'across layouts' => fn () => [TextInput::make('value'), Section::make('Other')->schema([TextInput::make('value')])],
]);

it('refuses two fields that share a state path inside a repeater row', function () {
    $form = new class extends Form
    {
        public function schema(): array
        {
            return [Repeater::make('items')->schema([TextInput::make('qty'), TextInput::make('qty')])];
        }
    };

    expect(fn () => $form::make())->toThrow(FormDefinitionException::class, '[data.items.{row}.qty]');
});

it('allows the same name in different repeaters', function () {
    $form = new class extends Form
    {
        public function schema(): array
        {
            return [TextInput::make('qty'), Repeater::make('items')->schema([TextInput::make('qty')])];
        }
    };

    expect($form::make()->getData())->toHaveKey('qty');
});

it('finds a field two repeater levels down', function () {
    $field = NestedRowsForm::make()->findComponent('data.sections.outer.lines.inner.qty');

    expect($field->getStatePath())->toBe('data.sections.outer.lines.inner.qty');
});

it('builds the same row keys every time, so a rebuilt form recognises its rows', function () {
    expect(array_keys(FixedRowsForm::make()->getData()['items']))
        ->toBe(array_keys(FixedRowsForm::make()->getData()['items']));
});

it('records the paths server closures read as dependencies', function () {
    $nodes = collect(OrderForm::make()->toSchema()['components'])->keyBy('key');

    expect($nodes['data.region_id']['deps'])->toBe(['data.country_id'])
        ->and($nodes['data.country_id']['live'])->toBe(['mode' => 'change', 'ms' => null])
        ->and($nodes['data.customer']['deps'])->toBe([]);
});

it('serializes conditions and computed expressions with absolute paths', function () {
    $nodes = collect(OrderForm::make()->toSchema()['components'])->keyBy('key');

    expect($nodes['data#2']['rules']['visible'])->toBe(['op' => 'eq', 'path' => 'data.type', 'value' => 'business'])
        ->and($nodes['data.total']['computed']['ast'])->toBe(['fn' => 'sum', 'args' => [['ref' => 'data.items.*.subtotal']]]);

    $template = collect($nodes['data.items']['props']->template)->keyBy('key');

    expect($template['data.items.{row}.subtotal']['computed']['ast'])
        ->toBe(['op' => '*', 'args' => [['ref' => 'data.items.{row}.qty'], ['ref' => 'data.items.{row}.price']]]);
});

it('encodes empty repeaters as JSON objects', function () {
    $json = json_encode(OrderForm::make()->toSchema());

    expect($json)->toContain('"items":{}')->toContain('"rows":{}');
});

it('builds anonymous static forms without a token', function () {
    $schema = Form::make([TextInput::make('name')->required()])->toSchema();

    expect($schema['token'])->toBeNull()->and($schema['id'])->toBe('form');
});

it('refuses live fields in anonymous forms', function () {
    Form::make([TextInput::make('name')->live()])->toSchema();
})->throws(FormDefinitionException::class);

it('serializes conditional text as a browser rule with no dependency or live trigger', function () {
    $form = Form::make([
        Toggle::make('is_gift'),
        TextInput::make('notes')->placeholder(When::eq('is_gift', true)->then('Gift message')->else('Notes')),
    ]);
    $nodes = collect($form->toSchema()['components'])->keyBy('key');

    expect($nodes['data.notes']['rules']['text']['placeholder'])->toBe([
        'when' => ['op' => 'eq', 'path' => 'data.is_gift', 'value' => true],
        'text' => 'Gift message',
        'otherwise' => 'Notes',
    ])
        ->and($nodes['data.notes']['deps'])->toBe([])
        ->and($nodes['data.is_gift']['live'])->toBeNull();
});

it('resolves conditional text on the server from the current state', function (bool $isGift, string $expected) {
    $form = Form::make([
        Toggle::make('is_gift')->default($isGift),
        TextInput::make('notes')
            ->label(When::eq('is_gift', true)->then('Gift note')->else('Note'))
            ->hint(When::eq('is_gift', true)->then('Gift note')->else('Note'))
            ->placeholder(When::eq('is_gift', true)->then('Gift note')->else('Note')),
    ]);
    $node = collect($form->toSchema()['components'])->firstWhere('key', 'data.notes');

    expect([$node['label'], $node['hint'], $node['placeholder']])->toBe([$expected, $expected, $expected]);
})->with([
    'condition passes' => [true, 'Gift note'],
    'condition fails' => [false, 'Note'],
]);

it('falls back to the default label when conditional text has no else branch', function () {
    $form = Form::make([
        Toggle::make('is_gift'),
        TextInput::make('gift_note')->label(When::eq('is_gift', true)->then('Message')),
    ]);
    $node = collect($form->toSchema()['components'])->firstWhere('key', 'data.gift_note');

    expect($node['label'])->toBe('Gift Note')
        ->and($node['rules']['text']['label']['otherwise'])->toBe('Gift Note');
});

it('leaves the rules untouched for fields without conditional text', function () {
    $node = Form::make([TextInput::make('notes')->placeholder('Notes')])->toSchema()['components'][0];

    expect($node['rules'])->not->toHaveKey('text');
});

it('resolves conditional text paths against the repeater row', function () {
    $form = Form::make([
        Repeater::make('items')->schema([
            Toggle::make('is_gift'),
            TextInput::make('notes')->placeholder(When::eq('is_gift', true)->then('Gift')->else('Notes')),
        ]),
    ]);
    $repeater = $form->toSchema()['components'][0];

    expect($repeater['props']->template[1]['rules']['text']['placeholder']['when']['path'])
        ->toBe('data.items.{row}.is_gift');
});
