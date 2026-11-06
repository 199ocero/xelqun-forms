<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Xelqun\Actions\Action;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Conditions\When;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Text;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\State\Set;
use Xelqun\Forms\Tests\Fixtures\Country;
use Xelqun\Forms\Tests\Fixtures\Order;
use Xelqun\Forms\Tests\Fixtures\Tag;

class CountryRules
{
    public static bool $canCreate = true;

    public static array $editable = [];

    public function create(?User $user): bool
    {
        return self::$canCreate;
    }

    public function update(?User $user, Country $country): bool
    {
        return in_array($country->name, self::$editable, true);
    }
}

class OptionForm extends Form
{
    public static array $calls = [];

    public function model(): ?string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [
            Select::make('country_id')
                ->relationship('country', 'name', fn ($query) => $query->where('name', '!=', 'Hidden land'))
                ->manageOptionForm([TextInput::make('name')->required()]),
            Select::make('tags')
                ->relationship('tags', 'name')
                ->multiple()
                ->createOptionForm([TextInput::make('name')->required()])
                ->createOptionAction(fn (Action $action) => $action->authorize(true)),
            Select::make('plain')
                ->options(['a' => 'A'])
                ->createOptionForm([TextInput::make('code')->required()])
                ->createOptionAction(fn (Action $action) => $action->authorize(true))
                ->createOptionUsing(function (array $data): string {
                    self::$calls[] = $data;

                    return strtolower($data['code']);
                }),
            Select::make('locked_id')
                ->relationship('country', 'name')
                ->disabled()
                ->createOptionForm([TextInput::make('name')])
                ->createOptionAction(fn (Action $action) => $action->authorize(true)),
            Select::make('broken')->options(['a' => 'A'])->createOptionForm([TextInput::make('name')])->createOptionAction(fn (Action $action) => $action->authorize(true)),
            Repeater::make('items')
                ->extraItemActions([
                    Action::make('double')->authorize(true)->icon('star')->action(fn (Get $get, Set $set) => $set('qty', $get('qty') * 2)),
                    Action::make('nope')->action(fn () => self::$calls[] = 'nope'),
                ])
                ->schema([
                    TextInput::make('qty'),
                    Select::make('country_id')
                        ->relationship('country', 'name')
                        ->createOptionForm([TextInput::make('name')->required()])
                        ->createOptionAction(fn (Action $action) => $action->authorize(true)),
                ]),
            Repeater::make('locked_rows')->readOnly()->extraItemActions([Action::make('run')->authorize(true)->action(fn () => self::$calls[] = 'locked')])->schema([TextInput::make('a')]),
        ];
    }
}

beforeEach(function () {
    Gate::policy(Country::class, CountryRules::class);
    CountryRules::$canCreate = true;
    CountryRules::$editable = [];
    OptionForm::$calls = [];
});

function optionAction(string $key, string $phase, array $state = [], ?array $data = null): TestResponse
{
    return test()->postJson(route('xelqun.forms.action'), [
        'token' => OptionForm::make()->makeToken(),
        'action' => $key,
        'phase' => $phase,
        'state' => ['data' => $state],
        'data' => $data,
        'deps' => (object) [],
    ]);
}

const CREATE = 'data#0.s0';
const EDIT = 'data#0.s1';

describe('create an option', function () {
    it('opens the fields from the modal', function () {
        expect(optionAction(CREATE, 'mount')->assertOk()->json('form.components.0.path'))->toBe('data.name');
    });

    it('creates the record, selects it and sends the new option', function () {
        $response = optionAction(CREATE, 'run', [], ['name' => 'Norway'])->assertOk();
        $country = Country::query()->firstOrFail();

        expect($country->name)->toBe('Norway')
            ->and($response->json('set'))->toBe(['data.country_id' => $country->getKey()])
            ->and($response->json('schema')['data.country_id']['props']['options'][0])->toMatchArray(['value' => $country->getKey(), 'label' => 'Norway']);
    });

    it('validates the fields first', function () {
        optionAction(CREATE, 'run', [], ['name' => null])->assertStatus(422)->assertJsonValidationErrors(['data.name']);

        expect(Country::query()->count())->toBe(0);
    });

    it('needs the policy, whatever the form allows', function () {
        CountryRules::$canCreate = false;

        optionAction(CREATE, 'run', [], ['name' => 'Norway'])->assertForbidden();

        expect(Country::query()->count())->toBe(0);
    });

    it('adds to a multiple select', function () {
        $first = Tag::query()->create(['name' => 'Old']);

        $key = 'data#1.s0';
        $response = optionAction($key, 'run', ['tags' => [$first->getKey()]], ['name' => 'New'])->assertOk();

        expect($response->json('set')['data.tags'])->toBe([$first->getKey(), Tag::query()->where('name', 'New')->value('id')]);
    });

    it('takes a closure, with no relationship', function () {
        optionAction('data#2.s0', 'run', [], ['code' => 'ABC'])->assertOk()->assertJsonPath('set', ['data.plain' => 'abc']);

        expect(OptionForm::$calls)->toBe([['code' => 'ABC']]);
    });

    it('says what is missing with neither', function () {
        $this->withoutExceptionHandling();

        optionAction('data#4.s0', 'run', [], ['name' => 'x']);
    })->throws(LogicException::class, 'needs createOptionUsing()');

    it('refuses on a disabled select', function () {
        optionAction('data#3.s0', 'run', [], ['name' => 'x'])->assertForbidden();
    });

    it('works inside a row, on that row', function () {
        $response = optionAction('data.items.r1#1.s0', 'run', ['items' => ['r1' => ['qty' => 1]]], ['name' => 'Norway'])->assertOk();

        expect($response->json('set'))->toBe(['data.items.r1.country_id' => Country::query()->value('id')]);
    });
});

describe('edit an option', function () {
    it('shows only with a value', function () {
        $country = Country::query()->create(['name' => 'Norway']);
        CountryRules::$editable = ['Norway'];

        expect(optionAction(EDIT, 'mount')->status())->toBe(403);

        optionAction(EDIT, 'mount', ['country_id' => $country->getKey()])->assertOk();
    });

    it('fills its fields from the record, and nothing else', function () {
        $country = Country::query()->create(['name' => 'Norway']);
        CountryRules::$editable = ['Norway'];

        $form = optionAction(EDIT, 'mount', ['country_id' => $country->getKey()])->json('form');

        expect($form['state']['data'])->toBe(['name' => 'Norway']);
    });

    it('saves the record and sends the new label', function () {
        $country = Country::query()->create(['name' => 'Norway']);
        CountryRules::$editable = ['Norway'];

        $response = optionAction(EDIT, 'run', ['country_id' => $country->getKey()], ['name' => 'Noreg'])->assertOk();

        expect($country->refresh()->name)->toBe('Noreg')
            ->and($response->json('schema')['data.country_id']['props']['options'][0]['label'])->toBe('Noreg');
    });

    it('checks the policy against the selected record', function () {
        $country = Country::query()->create(['name' => 'Norway']);

        optionAction(EDIT, 'run', ['country_id' => $country->getKey()], ['name' => 'Taken'])->assertForbidden();

        expect($country->refresh()->name)->toBe('Norway');
    });

    it('never reaches a record outside the relationship query', function () {
        $hidden = Country::query()->create(['name' => 'Hidden land']);
        CountryRules::$editable = ['Hidden land'];

        optionAction(EDIT, 'mount', ['country_id' => $hidden->getKey()])->assertForbidden();
        optionAction(EDIT, 'run', ['country_id' => $hidden->getKey()], ['name' => 'Mine'])->assertForbidden();

        expect($hidden->refresh()->name)->toBe('Hidden land');
    });

    it('re-checks the record on each request from its modal', function () {
        $country = Country::query()->create(['name' => 'Norway']);
        CountryRules::$editable = ['Norway'];

        $token = optionAction(EDIT, 'mount', ['country_id' => $country->getKey()])->json('form.token');
        CountryRules::$editable = [];

        $this->postJson(route('xelqun.forms.update'), [
            'token' => $token,
            'seq' => 1,
            'state' => ['data' => ['name' => 'x']],
            'changed' => [],
            'deps' => (object) [],
        ])->assertForbidden();
    });

    it('runs once per value, not once per check', function () {
        $country = Country::query()->create(['name' => 'Norway']);
        CountryRules::$editable = ['Norway'];
        $queries = 0;

        DB::listen(function ($query) use (&$queries): void {
            $queries += str_contains($query->sql, 'countries') ? 1 : 0;
        });

        optionAction(EDIT, 'mount', ['country_id' => $country->getKey()])->assertOk();

        expect($queries)->toBeLessThan(8);
    });
});

it('takes custom saving and filling', function () {
    $country = Country::query()->create(['name' => 'Norway']);

    $form = new class extends OptionForm
    {
        public function schema(): array
        {
            return [
                Select::make('country_id')
                    ->relationship('country', 'name')
                    ->editOptionForm([TextInput::make('name')])
                    ->fillEditOptionActionFormUsing(fn (Country $record): array => ['name' => strtoupper($record->name)])
                    ->updateOptionUsing(fn (array $data, Country $record) => $record->update(['name' => "{$data['name']}!"]))
                    ->editOptionModalHeading('Change it')
                    ->editOptionAction(fn (Action $action) => $action->authorize(true)),
            ];
        }
    };

    $call = fn (string $phase, ?array $data = null) => test()->postJson(route('xelqun.forms.action'), [
        'token' => $form::make()->makeToken(),
        'action' => 'data#0.s0',
        'phase' => $phase,
        'state' => ['data' => ['country_id' => $country->getKey()]],
        'data' => $data,
        'deps' => (object) [],
    ]);

    expect($call('mount')->json('form.state.data'))->toBe(['name' => 'NORWAY']);

    $call('run', ['name' => 'Noreg'])->assertOk();

    expect($country->refresh()->name)->toBe('Noreg!');
});

describe('item actions', function () {
    it('runs on the row it was clicked in', function () {
        optionAction('data.items.r1#a0', 'run', ['items' => ['r1' => ['qty' => 2], 'r2' => ['qty' => 5]]])
            ->assertOk()
            ->assertJsonPath('set', ['data.items.r1.qty' => 4]);
    });

    it('sends the actions in each row and in the template', function () {
        $form = new class extends Form
        {
            public function schema(): array
            {
                return [Repeater::make('items')->default([['a' => 1]])->extraItemActions([Action::make('go')->authorize(true)])->schema([TextInput::make('a')])];
            }
        };

        $props = $form::make()->toSchema()['components'][0]['props'];
        $row = collect($props->rows)->first();

        expect(end($row)['slot'])->toBe('itemAction')
            ->and(end($props->template)['key'])->toContain('{row}')
            ->and($row[0]['path'])->toContain('.a');
    });

    it('survives extraItemActions() before schema()', function () {
        $form = new class extends Form
        {
            public function schema(): array
            {
                return [Repeater::make('items')->schema([TextInput::make('a')])->extraItemActions([Action::make('go')->authorize(true)])];
            }
        };

        expect($form::make()->toSchema()['components'][0]['props']->template)->toHaveCount(2);
    });

    it('still needs authorize()', function () {
        optionAction('data.items.r1#a1', 'run', ['items' => ['r1' => ['qty' => 2]]])->assertForbidden();

        expect(OptionForm::$calls)->toBe([]);
    });

    it('refuses a row that is not there, and a read-only repeater', function () {
        optionAction('data.items.forged#a0', 'run', ['items' => []])->assertForbidden();
        optionAction('data.locked_rows.r1#a0', 'run', ['locked_rows' => ['r1' => ['a' => 1]]])->assertForbidden();

        expect(OptionForm::$calls)->toBe([]);
    });

    it('needs a form class', function () {
        expect(fn () => Form::make([Repeater::make('items')->extraItemActions([Action::make('go')->authorize(true)])->schema([TextInput::make('a')])])->toSchema())
            ->toThrow(FormDefinitionException::class);
    });
});

describe('actions beside a field', function () {
    class BesideFieldForm extends Form
    {
        public static array $calls = [];

        public function schema(): array
        {
            return [
                TextInput::make('lock'),
                Select::make('a')
                    ->options(['x' => 'X'])
                    ->disabledWhen(When::filled('lock'))
                    ->suffixAction(Action::make('go')->authorize(true)->action(fn () => self::$calls[] = 'ran')),
                Select::make('b')
                    ->options(['x' => 'X'])
                    ->createOptionForm([TextInput::make('name')])
                    ->createOptionUsing(fn (): string => 'x')
                    ->createOptionAction(function (Action $action): void {
                        $action->authorize(true);
                    }),
            ];
        }
    }

    function besideField(string $key, array $state, ?array $data = null): TestResponse
    {
        return test()->postJson(route('xelqun.forms.action'), [
            'token' => BesideFieldForm::make()->makeToken(),
            'action' => $key,
            'phase' => 'run',
            'state' => ['data' => $state],
            'data' => $data,
            'deps' => (object) [],
        ]);
    }

    beforeEach(fn () => BesideFieldForm::$calls = []);

    it('refuses while a condition disables the field', function () {
        besideField('data#1.s0', ['lock' => 'yes'])->assertForbidden();

        expect(BesideFieldForm::$calls)->toBe([]);
    });

    it('runs once the condition lets go', function () {
        besideField('data#1.s0', ['lock' => null])->assertOk();

        expect(BesideFieldForm::$calls)->toBe(['ran']);
    });

    it('takes a callback that changes the action in place', function () {
        besideField('data#2.s0', [], ['name' => 'a'])->assertOk();
    });
});

it('puts prefix and suffix actions in their own slots', function () {
    $form = new class extends Form
    {
        public function schema(): array
        {
            return [
                TextInput::make('code')
                    ->prefixAction(Action::make('one')->authorize(true))
                    ->suffixActions([Action::make('two')->authorize(true), Action::make('three')->authorize(true)])
                    ->afterContent(Text::make('kept')),
            ];
        }
    };

    expect(collect($form::make()->toSchema()['components'][0]['children'])->pluck('slot')->all())
        ->toBe(['prefixAction', 'suffixAction', 'suffixAction', 'afterContent']);
});
