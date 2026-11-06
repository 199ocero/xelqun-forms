<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Actions\Action;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Actions;
use Xelqun\Forms\Layouts\Section;
use Xelqun\Forms\Security\FormToken;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\State\Set;
use Xelqun\Forms\Tests\Fixtures\Country;

class ActionTestForm extends Form
{
    public static array $calls = [];

    public static bool $allowed = true;

    public function schema(): array
    {
        return [
            TextInput::make('secret')->disabled()->default('server'),
            TextInput::make('hidden_value')->hidden()->default('do not leak'),
            TextInput::make('note'),
            Actions::make([
                Action::make('apply')
                    ->authorize(true)
                    ->fillForm(['amount' => 5])
                    ->schema([
                        TextInput::make('amount')->numeric()->required(),
                        TextInput::make('reason'),
                        Select::make('tag')->searchable()->getSearchResultsUsing(fn () => ['a' => 'A']),
                        TextInput::make('doubled')->live()->afterStateUpdated(fn (Get $get, Set $set) => $set('reason', 'x'.$get('amount'))),
                        FileUpload::make('file')->disk('local')->image(),
                    ])
                    ->action(function (array $data, Set $set) {
                        self::$calls[] = $data;
                        $set('note', 'Got '.$data['amount']);
                    }),
                Action::make('plain')
                    ->authorize(true)
                    ->action(function (Get $get, Set $set) {
                        self::$calls[] = $get('secret');
                        $set('note', 'Done');
                    }),
                Action::make('other_file')->authorize(true)->schema([FileUpload::make('file')->disk('local')->acceptedFileTypes(['text/plain'])]),
                Action::make('denied')->action(fn () => self::$calls[] = 'denied'),
                Action::make('gated')->authorize(fn () => self::$allowed)->schema([TextInput::make('a')])->action(fn () => self::$calls[] = 'gated'),
                Action::make('policy')->authorize('run-it')->model(Country::class)->action(fn () => self::$calls[] = 'policy'),
                Action::make('no_policy')->authorize('nothing-defined')->model(Country::class)->action(fn () => self::$calls[] = 'no_policy'),
                Action::make('disabled')->authorize(true)->disabled()->action(fn () => self::$calls[] = 'disabled'),
                Action::make('hidden')->authorize(true)->hidden(fn (Get $get) => $get('secret') !== 'admin')->action(fn () => self::$calls[] = 'hidden'),
                Action::make('broken')->authorize(true)->action(function () {
                    Country::create(['name' => 'Atlantis']);

                    throw ValidationException::withMessages(['data.note' => 'Not today.']);
                }),
            ]),
            TextInput::make('locked')->disabled()->afterContent(Action::make('in_locked')->authorize(true)->action(fn () => self::$calls[] = 'in_locked')),
            Repeater::make('items')->schema([
                TextInput::make('qty'),
                Actions::make([
                    Action::make('double')->authorize(true)->action(function (Get $get, Set $set, mixed $record) {
                        self::$calls[] = $record;
                        $set('qty', $get('qty') * 2);
                    }),
                ]),
            ]),
        ];
    }
}

beforeEach(function () {
    ActionTestForm::$calls = [];
    ActionTestForm::$allowed = true;
    Storage::fake('local');
});

const APPLY = 'data#3.0';
const PLAIN = 'data#3.1';
const OTHER_FILE = 'data#3.2';
const DENIED = 'data#3.3';
const GATED = 'data#3.4';
const POLICY = 'data#3.5';
const NO_POLICY = 'data#3.6';
const DISABLED = 'data#3.7';
const HIDDEN = 'data#3.8';
const BROKEN = 'data#3.9';

function callAction(string $key, string $phase, array $state = [], ?array $data = null, ?string $token = null): TestResponse
{
    return test()->postJson(route('xelqun.forms.action'), [
        'token' => $token ?? ActionTestForm::make()->makeToken(),
        'action' => $key,
        'phase' => $phase,
        'state' => ['data' => $state],
        'data' => $data,
        'deps' => (object) [],
    ]);
}

it('opens a modal with its own schema and token', function () {
    $form = callAction(APPLY, 'mount')->assertOk()->json('form');

    expect($form['id'])->toBe('action-apply')
        ->and($form['state']['data']['amount'])->toBe(5)
        ->and(collect($form['components'])->pluck('path'))->toContain('data.amount')
        ->and($form['token'])->not->toBe(ActionTestForm::make()->makeToken());
});

it('has nothing to open without fields', function () {
    callAction(PLAIN, 'mount')->assertOk()->assertJsonPath('form', null);
});

it('never shows the parent form values in the modal', function () {
    expect(callAction(APPLY, 'mount', ['note' => 'mine'])->getContent())->not->toContain('do not leak')->not->toContain('mine');
});

it('runs an action, and sends back what it set', function () {
    callAction(PLAIN, 'run')->assertOk()->assertJsonPath('set', ['data.note' => 'Done']);

    expect(ActionTestForm::$calls)->toBe(['server']);
});

it('gives the action trusted state, whatever the browser posted', function () {
    callAction(PLAIN, 'run', ['secret' => 'tampered'])->assertOk();

    expect(ActionTestForm::$calls)->toBe(['server']);
});

it('validates the modal and passes only its known fields', function () {
    callAction(APPLY, 'run', [], ['amount' => '7', 'nope' => 'x'])->assertOk()->assertJsonPath('set', ['data.note' => 'Got 7']);

    expect(ActionTestForm::$calls[0])->toHaveKey('amount')->not->toHaveKey('nope');
});

it('keeps the modal open with its errors when it is invalid', function () {
    callAction(APPLY, 'run', [], ['amount' => null])->assertStatus(422)->assertJsonValidationErrors(['data.amount']);

    expect(ActionTestForm::$calls)->toBe([]);
});

it('checks the modal even when no data is posted', function () {
    callAction(APPLY, 'run')->assertStatus(422);

    expect(ActionTestForm::$calls)->toBe([]);
});

it('rolls everything back when an action fails', function () {
    callAction(BROKEN, 'run')->assertStatus(422)->assertJsonValidationErrors(['data.note' => 'Not today.']);

    expect(Country::query()->count())->toBe(0);
});

it('works on the row it sits in', function () {
    callAction('data.items.r1#1.0', 'run', ['items' => ['r1' => ['qty' => 4]]])
        ->assertOk()
        ->assertJsonPath('set', ['data.items.r1.qty' => 8]);
});

it('refuses a row the state does not hold', function () {
    callAction('data.items.forged#1.0', 'run', ['items' => ['r1' => ['qty' => 4]]])->assertForbidden();

    expect(ActionTestForm::$calls)->toBe([]);
});

it('refuses what is not a declared action', function (string $key) {
    callAction($key, 'run')->assertNotFound();
})->with(['a field' => 'data.note', 'a layout' => 'data#3', 'nothing' => 'data#99']);

it('refuses until authorize() says yes', function (string $key, string $phase) {
    callAction($key, $phase)->assertForbidden();

    expect(ActionTestForm::$calls)->toBe([]);
})->with([
    'no authorize() to run' => [DENIED, 'run'],
    'no authorize() to open' => [DENIED, 'mount'],
    'no policy' => [NO_POLICY, 'run'],
    'disabled' => [DISABLED, 'run'],
    'hidden' => [HIDDEN, 'run'],
    'inside a disabled field' => ['data#4.s0', 'run'],
]);

it('runs on a policy ability', function () {
    Gate::define('run-it', fn (?User $user, string $model): bool => $model === Country::class);

    callAction(POLICY, 'run')->assertOk();

    expect(ActionTestForm::$calls)->toBe(['policy']);
});

it('does not let tampered state open a hidden action', function () {
    callAction(HIDDEN, 'run', ['secret' => 'admin'])->assertForbidden();
});

it('hides what the user may not use', function () {
    $keys = collect(ActionTestForm::make()->toSchema()['components'][3]['children'])
        ->reject(fn (array $node): bool => $node['hidden'])
        ->pluck('props.label');

    expect($keys->all())->not->toContain('Denied')->not->toContain('No Policy')->not->toContain('Hidden');
});

it('refuses a bad token and a token of another user', function () {
    callAction(PLAIN, 'run', token: 'nonsense')->assertForbidden();

    $token = ActionTestForm::make()->makeToken();
    $this->actingAs((new User)->forceFill(['id' => 5]));

    callAction(PLAIN, 'run', token: $token)->assertForbidden();
    expect(ActionTestForm::$calls)->toBe([]);
});

describe('requests from inside a modal', function () {
    /**
     * An upload belongs to the session that made it, and a test request starts a
     * new one unless the cookie says otherwise.
     */
    function keepOneSession(): void
    {
        test()->withCredentials()->withCookie(config('session.cookie'), str_repeat('a', 40));
    }

    function modalToken(string $key = APPLY): string
    {
        return callAction($key, 'mount')->json('form.token');
    }

    it('runs live updates against the modal', function () {
        $this->postJson(route('xelqun.forms.update'), [
            'token' => modalToken(),
            'seq' => 1,
            'state' => ['data' => ['amount' => 9]],
            'changed' => ['data.doubled'],
            'deps' => (object) [],
        ])->assertOk()->assertJsonPath('set', ['data.reason' => 'x9']);
    });

    it('searches inside the modal', function () {
        $this->postJson(route('xelqun.forms.search'), [
            'token' => modalToken(),
            'path' => 'data.tag',
            'search' => 'a',
        ])->assertOk()->assertJsonPath('options.0.value', 'a');
    });

    it('checks the action again on each one', function () {
        $token = modalToken(GATED);
        ActionTestForm::$allowed = false;

        $this->postJson(route('xelqun.forms.update'), [
            'token' => $token,
            'seq' => 1,
            'state' => ['data' => []],
            'changed' => [],
            'deps' => (object) [],
        ])->assertForbidden();
    });

    it('rebuilds the parent from the state it was opened with', function () {
        $token = callAction('data.items.r1#1.0', 'mount', ['items' => ['r1' => ['qty' => 4]]])->assertOk()->json('form');

        expect($token)->toBeNull();

        $decoded = app(FormTokenEncrypter::class)->decrypt(callAction(APPLY, 'mount', ['note' => 'kept'])->json('form.token'));

        expect($decoded->formClass)->toBe(ActionTestForm::class)
            ->and($decoded->actions[0]['k'])->toBe(APPLY)
            ->and($decoded->actions[0]['s'])->toBe(['note' => 'kept']);
    });

    it('does not let a token name a class that is not a form', function () {
        $token = app(FormTokenEncrypter::class)->encrypt(new FormToken(stdClass::class, null, null, [], null, [['k' => APPLY, 's' => []]]));

        callAction(APPLY, 'run', token: $token)->assertForbidden();
    });

    it('keeps an upload to the modal it was made for', function () {
        keepOneSession();

        $upload = $this->post(route('xelqun.forms.upload'), [
            'token' => modalToken(),
            'path' => 'data.file',
            'file' => UploadedFile::fake()->image('a.png'),
        ])->assertOk()->json('token');

        $response = callAction(OTHER_FILE, 'run', [], ['file' => $upload])->assertStatus(422);

        expect($response->json('errors')['data.file'][0])->toContain('expired');
    });

    it('stores an upload when the action runs', function () {
        keepOneSession();

        $upload = $this->post(route('xelqun.forms.upload'), [
            'token' => modalToken(),
            'path' => 'data.file',
            'file' => UploadedFile::fake()->image('a.png'),
        ])->assertOk()->json('token');

        callAction(APPLY, 'run', [], ['amount' => 1, 'file' => $upload])->assertOk();

        Storage::disk('local')->assertExists(ActionTestForm::$calls[0]['file']);
    });
});

it('needs a form class, wherever it sits', function (object $component) {
    expect(fn () => Form::make([$component])->toSchema())->toThrow(FormDefinitionException::class);
})->with([
    'in a layout' => fn () => Actions::make([Action::make('a')->authorize(true)]),
    'in a slot' => fn () => TextInput::make('a')->belowLabel(Action::make('b')->authorize(true)),
    'in a repeater slot' => fn () => Repeater::make('a')->belowLabel(Action::make('b')->authorize(true))->schema([TextInput::make('c')]),
]);

describe('where an action sits', function () {
    class ActionPlacementForm extends Form
    {
        public static array $calls = [];

        public function schema(): array
        {
            return [
                Section::make('Hidden')->hidden()->schema([
                    Actions::make([Action::make('a')->authorize(true)->action(fn () => self::$calls[] = 'in hidden section')]),
                ]),
                Repeater::make('locked')->readOnly()->schema([
                    Actions::make([Action::make('b')->authorize(true)->action(fn () => self::$calls[] = 'read-only row')]),
                ]),
                Repeater::make('rows')->schema([
                    TextInput::make('qty'),
                    Actions::make([Action::make('c')->authorize(true)->schema([TextInput::make('m')->live()])->action(fn () => null)]),
                ]),
            ];
        }
    }

    function placed(string $key, array $state, string $phase = 'run'): TestResponse
    {
        return test()->postJson(route('xelqun.forms.action'), [
            'token' => ActionPlacementForm::make()->makeToken(),
            'action' => $key,
            'phase' => $phase,
            'state' => ['data' => $state],
            'deps' => (object) [],
        ]);
    }

    it('refuses an action inside a hidden section', function () {
        placed('data#0.0.0', [])->assertForbidden();
    });

    it('refuses an action in a read-only row', function () {
        placed('data.locked.r1#0.0', ['locked' => ['r1' => []]])->assertForbidden();

        expect(ActionPlacementForm::$calls)->toBe([]);
    });

    it('rebuilds a row modal from the state it was opened with', function () {
        $token = placed('data.rows.r1#1.0', ['rows' => ['r1' => ['qty' => 1]]], 'mount')->assertOk()->json('form.token');

        $this->postJson(route('xelqun.forms.update'), [
            'token' => $token,
            'seq' => 1,
            'state' => ['data' => []],
            'changed' => [],
            'deps' => (object) [],
        ])->assertOk();
    });
});
