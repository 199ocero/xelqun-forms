<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Xelqun\Actions\Action;
use Xelqun\Actions\ActionSet;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Tests\Fixtures\Country;

class CountryTestActions extends ActionSet
{
    public static array $calls = [];

    public function model(): string
    {
        return Country::class;
    }

    public function actions(): array
    {
        return [
            Action::make('rename')
                ->authorize(true)
                ->schema([TextInput::make('name')->required()])
                ->action(fn (Country $record, array $data) => $record->update($data)),
            Action::make('refuse')
                ->authorize(true)
                ->action(fn () => throw ValidationException::withMessages(['data' => 'Not today.'])),
            Action::make('denied')
                ->label('Secret label')
                ->action(fn () => self::$calls[] = 'denied'),
            Action::make('ping')
                ->authorize(true)
                ->action(fn (?Country $record) => self::$calls[] = $record),
        ];
    }
}

class StyledTestActions extends ActionSet
{
    public function actions(): array
    {
        return [
            Action::make('plain')->authorize(true),
            Action::make('styled')
                ->authorize(true)
                ->modalIcon('heart')
                ->modalIconColor('success')
                ->modalAlignment('center')
                ->modalFooterActionsAlignment('full')
                ->modalCloseButton(false)
                ->closeModalByClickingAway(false),
        ];
    }
}

class ChangingTestActions extends ActionSet
{
    public static bool $published = false;

    public static array $ran = [];

    public function actions(): array
    {
        return [
            ...(self::$published ? [Action::make('unpublish')->authorize(true)->action(fn () => self::$ran[] = 'unpublish')] : []),
            Action::make('archive')->authorize(true)->action(fn () => self::$ran[] = 'archive'),
            Action::make('delete')->authorize(true)->action(fn () => self::$ran[] = 'delete'),
        ];
    }
}

class PolicyTestActions extends ActionSet
{
    public function model(): string
    {
        return Country::class;
    }

    public function actions(): array
    {
        return [Action::make('create')->authorize('create-country')];
    }
}

class GateTestActions extends ActionSet
{
    public function actions(): array
    {
        return [Action::make('export')->authorize('export')];
    }
}

class DuplicateTestActions extends ActionSet
{
    public function actions(): array
    {
        return [Action::make('same')->authorize(true), Action::make('same')->authorize(true)];
    }
}

class NoModelTestActions extends ActionSet
{
    public function actions(): array
    {
        return [Action::make('go')->authorize(true)];
    }
}

class RedirectTestActions extends ActionSet
{
    public function model(): string
    {
        return Country::class;
    }

    public function actions(): array
    {
        return [
            Action::make('delete')
                ->authorize(true)
                ->successRedirectUrl(fn (Country $record) => "/countries?deleted={$record->name}")
                ->action(fn (Country $record) => $record->delete()),
            Action::make('unsafe')
                ->authorize(true)
                ->successRedirectUrl('javascript:alert(1)')
                ->action(fn () => null),
        ];
    }
}

const RENAME = 'data#rename';
const REFUSE = 'data#refuse';
const DENIED_ACTION = 'data#denied';
const PING = 'data#ping';

beforeEach(function () {
    CountryTestActions::$calls = [];
});

function callActionSet(ActionSet $actions, string $key, string $phase = 'run', ?array $data = null): TestResponse
{
    return test()->postJson(route('xelqun.forms.action'), [
        'token' => $actions->makeToken(),
        'action' => $key,
        'phase' => $phase,
        'state' => ['data' => []],
        'data' => $data,
        'deps' => (object) [],
    ]);
}

it('sends the actions with no fields and hides what the viewer may not use', function () {
    $schema = CountryTestActions::make()->toSchema();

    expect($schema['token'])->toBeString()
        ->and($schema['state']['data'])->toEqual((object) [])
        ->and(array_column($schema['components'], 'hidden'))->toBe([false, false, true, false])
        ->and(json_encode($schema))->not->toContain('Secret label');
});

it('sends how a modal should look, and nothing for what stays the default', function () {
    [$plain, $styled] = array_column(StyledTestActions::make()->toSchema()['components'], 'props');

    expect($styled)->toMatchArray([
        'modalIcon' => 'heart',
        'modalIconColor' => 'success',
        'alignment' => 'center',
        'footerAlignment' => 'full',
        'closeButton' => false,
        'closeOnClickAway' => false,
    ])->and($plain)->not->toHaveKeys(['modalIcon', 'modalIconColor', 'alignment', 'footerAlignment', 'closeButton', 'closeOnClickAway']);
});

it('runs the action that was clicked, even when the list has changed since', function () {
    ChangingTestActions::$ran = [];
    ChangingTestActions::$published = false;

    $schema = ChangingTestActions::make()->toSchema();
    $archive = $schema['components'][0]['key'];

    ChangingTestActions::$published = true;

    test()->postJson(route('xelqun.forms.action'), [
        'token' => $schema['token'],
        'action' => $archive,
        'phase' => 'run',
        'state' => ['data' => []],
        'deps' => (object) [],
    ])->assertOk();

    expect(ChangingTestActions::$ran)->toBe(['archive']);
});

it('refuses two actions with one name', function () {
    DuplicateTestActions::make()->toSchema();
})->throws(FormDefinitionException::class, 'two actions with the same name');

it('asks a policy about the model of the set when there is no record', function () {
    Gate::define('create-country', fn (?User $user, string $model): bool => $model === Country::class);

    expect(PolicyTestActions::make()->toSchema()['components'][0]['hidden'])->toBeFalse();
});

it('asks a plain gate when there is no model or record to ask a policy about', function () {
    expect(GateTestActions::make()->toSchema()['components'][0]['hidden'])->toBeTrue();

    Gate::define('export', fn (?User $user): bool => true);

    expect(GateTestActions::make()->toSchema()['components'][0]['hidden'])->toBeFalse();
});

it('hides, rather than fails, when the gate wants a model it can\'t have', function () {
    Gate::define('export', fn (User $user, Country $country): bool => true);

    expect(GateTestActions::make()->toSchema()['components'][0]['hidden'])->toBeTrue();
});

it('fails on the page, not the click, when a record has no model', function () {
    NoModelTestActions::make(Country::create(['name' => 'Atlantis']))->toSchema();
})->throws(FormDefinitionException::class, 'must define model()');

it('sends the user away after an action, with the record that is gone', function () {
    $country = Country::create(['name' => 'Atlantis']);

    callActionSet(RedirectTestActions::make($country), 'data#delete')
        ->assertOk()
        ->assertJsonPath('redirect', '/countries?deleted=Atlantis');

    expect(Country::query()->whereKey($country->getKey())->exists())->toBeFalse();
});

it('ignores an unsafe redirect', function () {
    callActionSet(RedirectTestActions::make(), 'data#unsafe')->assertOk()->assertJsonPath('redirect', null);
});

it('runs an action with no record', function () {
    callActionSet(CountryTestActions::make(), PING)->assertOk();

    expect(CountryTestActions::$calls)->toBe([null]);
});

it('finds the record again from the token', function () {
    $country = Country::create(['name' => 'Atlantis']);

    callActionSet(CountryTestActions::make($country), PING)->assertOk();

    expect(CountryTestActions::$calls[0]->is($country))->toBeTrue();
});

it('opens a modal, checks its fields and then runs the action', function () {
    $country = Country::create(['name' => 'Atlantis']);
    $actions = CountryTestActions::make($country);

    expect(callActionSet($actions, RENAME, 'mount')->assertOk()->json('form.id'))->toBe('action-rename');

    callActionSet($actions, RENAME, data: ['name' => ''])->assertUnprocessable();
    expect($country->fresh()->name)->toBe('Atlantis');

    callActionSet($actions, RENAME, data: ['name' => 'Lemuria'])->assertOk();
    expect($country->fresh()->name)->toBe('Lemuria');
});

it('refuses an action the viewer may not use', function () {
    callActionSet(CountryTestActions::make(), DENIED_ACTION)->assertForbidden();

    expect(CountryTestActions::$calls)->toBe([]);
});

it('reports an error the action raises', function () {
    callActionSet(CountryTestActions::make(), REFUSE)->assertUnprocessable()->assertJsonPath('errors.data.0', 'Not today.');
});
