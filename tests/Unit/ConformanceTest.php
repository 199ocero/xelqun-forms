<?php

use Xelqun\Forms\Conditions\Condition;
use Xelqun\Forms\Expressions\Expression;
use Xelqun\Forms\Expressions\ExpressionEvaluator;
use Xelqun\Forms\Support\StatePath;

/**
 * The same fixtures run in tests/js/conformance.test.ts, so the PHP and
 * browser evaluators can never drift apart.
 */
function conformanceCases(string $file): array
{
    $cases = json_decode(file_get_contents(__DIR__.'/../Fixtures/conformance/'.$file), true);

    return collect($cases)->mapWithKeys(fn (array $case) => [$case['name'] => [$case]])->all();
}

it('evaluates conditions', function (array $case) {
    expect(Condition::evaluateTree($case['condition'], $case['state']))->toBe($case['expected']);
})->with(conformanceCases('conditions.json'));

it('parses expressions into the stored AST', function (array $case) {
    expect(Expression::make($case['src'])->resolve('data'))->toEqual($case['ast']);
})->with(conformanceCases('expressions.json'));

it('evaluates expressions', function (array $case) {
    expect(ExpressionEvaluator::evaluate($case['ast'], $case['state']))->toBe($case['expected']);
})->with(conformanceCases('expressions.json'));

it('decides whether two paths touch the same state', function (array $case) {
    expect(StatePath::overlaps($case['first'], $case['second']))->toBe($case['expected']);
})->with(conformanceCases('paths-overlap.json'));

it('fills in the row placeholder of one repeater level', function (array $case) {
    expect(StatePath::replaceRow($case['value'], $case['rowKey'], $case['placeholder']))->toBe($case['expected']);
})->with(conformanceCases('paths-replace-row.json'));
