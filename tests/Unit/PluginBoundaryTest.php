<?php

use Illuminate\Support\Facades\File;

/**
 * Plugins can only do what the core lets the contracts do, so a check on one
 * of our own fields would quietly put a feature out of their reach again.
 */
function concreteFieldClasses(): array
{
    return collect(File::files(__DIR__.'/../../src/Components'))
        ->map(fn (SplFileInfo $file): string => $file->getBasename('.php'))
        ->reject(fn (string $class): bool => in_array($class, ['Component', 'Field'], true))
        ->values()
        ->all();
}

it('has no instanceof check on a concrete field in the core', function (): void {
    $pattern = '/instanceof\s+\\\\?(?:[\w\\\\]*\\\\)?('.implode('|', concreteFieldClasses()).')\b/';
    $found = [];

    foreach (File::allFiles(__DIR__.'/../../src') as $file) {
        if (preg_match_all($pattern, $file->getContents(), $matches) > 0) {
            $found[$file->getRelativePathname()] = $matches[0];
        }
    }

    expect($found)->toBe([]);
});

it('has no check on a built-in field type in the client core', function (): void {
    $types = 'text-input|textarea|select|checkbox|toggle|radio|date-picker|file-upload|repeater';
    $found = [];

    foreach (File::allFiles(__DIR__.'/../../resources/js/core') as $file) {
        if (preg_match_all("/\.type\s*[!=]==\s*'({$types})'/", $file->getContents(), $matches) > 0) {
            $found[$file->getRelativePathname()] = $matches[0];
        }
    }

    expect($found)->toBe([]);
});

it('declares whether each class plugins see is public API', function (string $directory): void {
    foreach (File::files(__DIR__.'/../../src/'.$directory) as $file) {
        expect($file->getContents())->toMatch('/^ \\* @(api|internal)\b/m');
    }
})->with(['Contracts', 'Components', 'Components/Concerns', 'Conditions', 'Layouts', 'State']);
