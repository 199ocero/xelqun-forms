<?php

use Xelqun\Forms\Support\StatePath;

it('resolves a path against its container', function (string $path, string $container, string $expected) {
    expect(StatePath::resolve($path, $container))->toBe($expected);
})->with([
    'bare name' => ['qty', 'data', 'data.qty'],
    'bare name in a row' => ['qty', 'data.items.u1', 'data.items.u1.qty'],
    'field named data' => ['data', 'data', 'data.data'],
    'field named data in a row' => ['data', 'data.items.u1', 'data.items.u1.data'],
    'path through a field named data' => ['data.*.qty', 'data', 'data.data.*.qty'],
    'form root' => ['$.qty', 'data.items.u1', 'data.qty'],
    'field named data at the form root' => ['$.data', 'data.items.u1', 'data.data'],
]);
