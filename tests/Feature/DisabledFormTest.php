<?php

use Illuminate\Http\UploadedFile;
use Xelqun\Actions\Action;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Actions;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\Tests\Fixtures\Country;

class ViewOnlyTestForm extends Form
{
    public static int $hookRuns = 0;

    public static int $actionRuns = 0;

    public static array $namesClosuresSaw = [];

    public function model(): string
    {
        return Country::class;
    }

    public function schema(): array
    {
        return [
            TextInput::make('name')->required()->live()->afterStateUpdated(function (): void {
                self::$hookRuns++;
            }),
            TextInput::make('note')->hidden(function (Get $get): bool {
                self::$namesClosuresSaw[] = $get('name');

                return false;
            }),
            Select::make('code')->searchable()->getSearchResultsUsing(fn (): array => ['pe' => 'Peru']),
            FileUpload::make('flag')->disk('local'),
            Repeater::make('cities')->schema([TextInput::make('city')])->default([['city' => 'Lima']]),
            Actions::make([
                Action::make('open')->authorize(true)->action(function (): void {
                    self::$actionRuns++;
                }),
            ]),
        ];
    }
}

function viewOnlyForm(): ViewOnlyTestForm
{
    return ViewOnlyTestForm::make(Country::query()->create(['name' => 'Peru']))->disabled();
}

beforeEach(function () {
    ViewOnlyTestForm::$hookRuns = 0;
    ViewOnlyTestForm::$actionRuns = 0;
    ViewOnlyTestForm::$namesClosuresSaw = [];
});

it('tells the browser every field is disabled', function () {
    $schema = viewOnlyForm()->toSchema();

    expect($schema['disabled'])->toBeTrue()
        ->and($schema['components'][0]['disabled'])->toBeTrue()
        ->and($schema['components'][4]['disabled'])->toBeTrue();
});

it('keeps a form that was not disabled as it was', function (ViewOnlyTestForm $form) {
    $schema = $form->toSchema();

    expect($schema)->not->toHaveKey('disabled')
        ->and($schema['components'][0]['disabled'])->toBeFalse();
})->with([
    'never called' => fn () => ViewOnlyTestForm::make(),
    'turned off' => fn () => ViewOnlyTestForm::make()->disabled(false),
]);

it('stays disabled when a later request rebuilds it from the token', function () {
    $rebuilt = app(FormTokenEncrypter::class)->resolveForm(viewOnlyForm()->makeToken());

    expect($rebuilt->isDisabled())->toBeTrue();
});

it('runs no hook for a live update on a field the browser was told is disabled', function () {
    test()->postJson(route('xelqun.forms.update'), [
        'token' => viewOnlyForm()->makeToken(),
        'seq' => 1,
        'changed' => ['data.name'],
        'state' => ['data' => ['name' => 'Tampered']],
        'deps' => (object) [],
    ])->assertOk();

    expect(ViewOnlyTestForm::$hookRuns)->toBe(0);
});

it('refuses to search a field of a disabled form, with a 404', function () {
    test()->postJson(route('xelqun.forms.search'), [
        'token' => viewOnlyForm()->makeToken(),
        'path' => 'data.code',
        'search' => 'pe',
    ])->assertNotFound();
});

it('refuses an upload to a field of a disabled form', function () {
    test()->postJson(route('xelqun.forms.upload'), [
        'token' => viewOnlyForm()->makeToken(),
        'path' => 'data.flag',
        'file' => UploadedFile::fake()->create('flag.txt'),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('drops what a tampered request posts, and skips the rules', function () {
    expect(viewOnlyForm()->validate(['data' => ['name' => 'Tampered']]))->not->toHaveKey('name');
});

it('never lets a closure read what a tampered request posts', function () {
    viewOnlyForm()->validate(['data' => ['name' => 'Tampered']]);

    expect(ViewOnlyTestForm::$namesClosuresSaw)->toContain('Peru')->not->toContain('Tampered');
});

it('refuses to save, and writes nothing', function () {
    $form = viewOnlyForm();

    expect(fn () => $form->save(['data' => ['name' => 'Tampered']]))->toThrow(LogicException::class, 'is disabled');
    expect($form->getRecord()->refresh()->name)->toBe('Peru');
});

it('disables the fields inside the rows of a repeater', function () {
    $form = ViewOnlyTestForm::make()->disabled();
    $cities = [];

    $form->walk(function ($component) use (&$cities): void {
        if ($component instanceof TextInput && str_ends_with($component->getStatePath(), '.city')) {
            $cities[] = $component->isDisabled();
        }
    });

    expect($cities)->toBe([true]);
});

it('still runs an action in an actions layout, which answers to its own authorize', function () {
    $form = viewOnlyForm();
    $open = $form->toSchema()['components'][5]['children'][0]['key'];

    test()->postJson(route('xelqun.forms.action'), [
        'token' => $form->makeToken(),
        'action' => $open,
        'phase' => 'run',
        'state' => ['data' => []],
        'deps' => (object) [],
    ])->assertOk();

    expect(ViewOnlyTestForm::$actionRuns)->toBe(1);
});
