<?php

use Xelqun\Forms\Expressions\Expression;
use Xelqun\Forms\Expressions\ExpressionException;

it('resolves names against the container and $. against the root', function () {
    expect(Expression::make('qty * $.rate')->resolve('data.items.u1'))->toBe([
        'op' => '*',
        'args' => [['ref' => 'data.items.u1.qty'], ['ref' => 'data.rate']],
    ]);
});

it('rejects unknown functions', function () {
    Expression::make('system("ls")');
})->throws(ExpressionException::class, 'Unknown function [system]');

it('rejects broken expressions', function (string $source) {
    Expression::make($source);
})->throws(ExpressionException::class)->with(['qty *', '(1 + 2', '1 2', '"open', '#']);
