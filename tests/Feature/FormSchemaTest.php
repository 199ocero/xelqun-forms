<?php

use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Tests\Fixtures\Country;
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
