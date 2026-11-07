<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Callout;
use Xelqun\Forms\Layouts\Flex;
use Xelqun\Forms\Layouts\FusedGroup;
use Xelqun\Forms\Layouts\Group;
use Xelqun\Forms\Layouts\Section;
use Xelqun\Forms\Layouts\Tab;
use Xelqun\Forms\Layouts\Tabs;
use Xelqun\Forms\Layouts\Text;
use Xelqun\Forms\Layouts\UnorderedList;
use Xelqun\Forms\Live\UpdateProcessor;
use Xelqun\Forms\State\Get;

function displayForm(array $components): Form
{
    $form = new class extends Form
    {
        public static array $components;

        public function schema(): array
        {
            return self::$components;
        }
    };
    $form::$components = $components;

    return $form::make();
}

it('opens the tab the URL names, and falls back to the first', function () {
    Route::get('/tabs', fn () => displayForm([
        Tabs::make()->persistTabInQueryString('section')->tabs([Tab::make('One'), Tab::make('Contact details')]),
    ])->toSchema()['components'][0]);

    $this->getJson('/tabs?section=contact-details')
        ->assertJsonPath('props.queryKey', 'section')
        ->assertJsonPath('props.startKey', 'data#0.1')
        ->assertJsonPath('children.1.props.slug', 'contact-details');
    $this->getJson('/tabs?section=nope')->assertJsonPath('props.startKey', null);
    $this->getJson('/tabs')->assertJsonPath('props.startKey', null);
});

it('sends slots as children that name their slot', function () {
    $node = displayForm([TextInput::make('name')->afterLabel('Optional')])->toSchema()['components'][0];

    expect($node['children'])->toHaveCount(1)
        ->and($node['children'][0])->toMatchArray(['type' => 'text', 'slot' => 'afterLabel'])
        ->and($node['children'][0]['props']->content)->toBe('Optional');
});

it('replaces a slot that is filled twice', function () {
    $node = displayForm([TextInput::make('name')->afterLabel('One')->afterLabel('Two')->belowLabel('Three')])->toSchema()['components'][0];

    expect(collect($node['children'])->map(fn (array $child): string => $child['props']->content)->all())->toBe(['Two', 'Three']);
});

it('refuses a field in a slot, because nothing would check or save it', function (mixed $content) {
    TextInput::make('name')->afterLabel($content);
})->with([
    'direct' => fn () => TextInput::make('secret'),
    'nested' => fn () => Group::make([TextInput::make('secret')]),
])->throws(FormDefinitionException::class, "can't go in the [afterLabel] slot");

it('updates slot text that reads state, on a repeater too', function () {
    $form = displayForm([
        TextInput::make('name'),
        Repeater::make('items')->belowLabel(fn (Get $get): string => 'For '.$get('name'))->schema([TextInput::make('qty')]),
    ]);
    $slot = $form->toSchema()['components'][1]['children'][0];

    expect($slot['deps'])->toBe(['data.name']);

    $patch = app(UpdateProcessor::class)->process($form, ['name' => 'Ada', 'items' => []], ['data.name'], [$slot['key'] => $slot['deps']], 1);

    expect($patch['schema'][$slot['key']]['props']->content)->toBe('For Ada');
});

it('keeps the keys of slots inside repeater rows apart', function () {
    $form = displayForm([
        Repeater::make('items')->default([['qty' => 1], ['qty' => 2]])->schema([
            TextInput::make('qty')->belowLabel(fn (Get $get): string => 'Qty '.$get('qty')),
        ]),
    ]);
    $rows = $form->toSchema()['components'][0]['props']->rows;
    $contents = collect($rows)->map(fn (array $row): string => $row[0]['children'][0]['props']->content)->values()->all();
    $keys = collect($rows)->map(fn (array $row): string => $row[0]['children'][0]['key'])->unique();

    expect($contents)->toBe(['Qty 1', 'Qty 2'])->and($keys)->toHaveCount(2);
});

it('leaves display components out of the submitted data', function () {
    $form = displayForm([
        Text::make('Note'),
        Callout::make('Heads up')->footer([TextInput::make('inside')]),
        FusedGroup::make([TextInput::make('city')->afterLabel('Slot')->required()]),
    ]);

    expect($form->validate(['data' => ['city' => 'Oslo', 'inside' => 'x', 'data.Note' => 'no']]))->toBe(['inside' => 'x', 'city' => 'Oslo']);
});

it('validates the fields of a fused group, with only their labels hidden', function () {
    $form = displayForm([FusedGroup::make([TextInput::make('city')->required()])->label('Location')]);
    $node = $form->toSchema()['components'][0];

    expect($node['label'])->toBe('Location')
        ->and($node['children'][0])->toMatchArray(['label' => 'City', 'hiddenLabel' => true])
        ->and(fn () => $form->validate(['data' => []]))->toThrow(ValidationException::class);
});

it('tells a flex child to keep its width only when asked', function () {
    $children = displayForm([Flex::make([TextInput::make('a'), TextInput::make('b')->grow(false), TextInput::make('c')->grow()])])
        ->toSchema()['components'][0]['children'];

    expect($children[0])->not->toHaveKey('grow')
        ->and($children[1]['grow'])->toBeFalse()
        ->and($children[2]['grow'])->toBeTrue();
});

it('gives a callout the icon of its status, unless it names one', function () {
    $nodes = displayForm([Callout::make('A')->danger(), Callout::make('B')->success()->icon('star'), Callout::make('C')])->toSchema()['components'];

    expect($nodes[0]['props']->icon)->toBe('circle-alert')
        ->and($nodes[1]['props']->icon)->toBe('star')
        ->and($nodes[2]['props']->icon)->toBeNull();
});

it('only treats text as HTML when PHP says it is', function () {
    $nodes = displayForm([Text::make('<b>x</b>'), Text::make(new HtmlString('<b>x</b>'))])->toSchema()['components'];

    expect($nodes[0]['props'])->content->toBe('<b>x</b>')->html->toBeNull()
        ->and($nodes[1]['props'])->content->toBeNull()->html->toBe('<b>x</b>');
});

it('turns plain strings into list items', function () {
    $node = displayForm([UnorderedList::make(['One', Text::make('Two')->color('danger')])])->toSchema()['components'][0];

    expect($node['children'])->toHaveCount(2)
        ->and($node['children'][0]['type'])->toBe('text')
        ->and($node['children'][1]['props']->color)->toBe('danger');
});

it('sends only the text that differs from English', function () {
    expect(displayForm([])->toSchema())->not->toHaveKey('messages');

    app('translator')->addLines(['client.Save' => 'Guardar', 'client.Delete' => 'Delete'], 'es', 'xelqun-forms');
    app()->setLocale('es');

    $schema = displayForm([])->toSchema();

    expect($schema['locale'])->toBe('es')
        ->and($schema['messages'])->toBe(['Save' => 'Guardar']);
});

it('writes the locale the way browsers need it', function () {
    app()->setLocale('pt_BR');

    expect(displayForm([])->toSchema()['locale'])->toBe('pt-BR');
});

it('patches nothing under a hidden parent, whatever keys the browser names', function () {
    $form = displayForm([
        TextInput::make('role'),
        Section::make('Admin')->hidden(fn (Get $get): bool => $get('role') !== 'admin')->schema([
            TextInput::make('note')->label(fn (): string => 'secret label'),
        ]),
        TextInput::make('name')->hidden(fn (Get $get): bool => $get('role') !== 'admin')->belowContent(fn (): string => 'secret slot'),
    ]);

    $patch = app(UpdateProcessor::class)->process($form, ['role' => 'user'], ['data.role'], [
        'data.note' => ['data.role'],
        'data#2.s0' => ['data.role'],
    ], 1);

    expect((array) $patch['schema'])->toBe([]);
});

it('still patches a field whose parent is showing', function () {
    $form = displayForm([
        TextInput::make('role'),
        Section::make('Admin')->schema([TextInput::make('note')->label(fn (Get $get): string => "For {$get('role')}")]),
    ]);

    $patch = app(UpdateProcessor::class)->process($form, ['role' => 'user'], ['data.role'], ['data.note' => ['data.role']], 1);

    expect($patch['schema']['data.note']['label'])->toBe('For user');
});

it('sends nothing for a locale with no translation file', function () {
    config(['app.fallback_locale' => 'none']);
    app('translator')->setFallback('none');
    app()->setLocale('xx');

    expect(displayForm([])->toSchema())->not->toHaveKey('messages');
});

it('has an English line for all the text in the browser, and none left over', function () {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../resources/js'));
    $used = [];

    foreach ($files as $file) {
        if (in_array($file->getExtension(), ['vue', 'ts'], true)) {
            preg_match_all("/\bt\(\s*(?:collapsed \? )?'([^']+)'(?: : '([^']+)')?/", (string) file_get_contents($file->getPathname()), $matches);
            array_push($used, ...$matches[1], ...array_filter($matches[2]));
        }
    }

    $lines = require __DIR__.'/../../resources/lang/en/client.php';

    expect(array_diff(array_unique($used), array_keys($lines)))->toBe([])
        ->and(array_diff(array_keys($lines), $used))->toBe([]);
});

it('asks the browser to warn about unsaved changes only when told to', function () {
    expect(displayForm([])->toSchema())->not->toHaveKey('unsavedChangesAlert')
        ->and(displayForm([])->unsavedChangesAlerts()->toSchema()['unsavedChangesAlert'])->toBeTrue();
});
