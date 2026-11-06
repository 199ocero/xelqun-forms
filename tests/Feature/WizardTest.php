<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\Checkbox;
use Xelqun\Forms\Components\Hidden;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Textarea;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Conditions\When;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Step;
use Xelqun\Forms\Layouts\Wizard;
use Xelqun\Forms\Live\UpdateProcessor;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\State\Set;

class WizardTestForm extends Form
{
    public static array $calls = [];

    public static bool $allowed = true;

    public function authorize($user, $record): bool
    {
        return self::$allowed;
    }

    public function schema(): array
    {
        return [
            TextInput::make('secret')->disabled()->default('server'),
            Wizard::make([
                Step::make('Account')
                    ->beforeValidation(function () {
                        self::$calls[] = 'before';
                    })
                    ->afterValidation(function (Set $set) {
                        self::$calls[] = 'after';
                        $set('welcome', 'Hello');
                    })
                    ->schema([
                        TextInput::make('name')->required()->minLength(2),
                        TextInput::make('confirm_name')->same('name'),
                        Repeater::make('items')->schema([TextInput::make('qty')->numeric()->required()]),
                    ]),
                Step::make('Plan')->schema([TextInput::make('plan')->required()]),
                Step::make('Blocked')
                    ->beforeValidation(fn () => throw ValidationException::withMessages(['data.gate' => 'Not today.']))
                    ->schema([TextInput::make('gate')]),
                Step::make('Business')
                    ->visibleWhen(When::eq('type', 'business'))
                    ->schema([TextInput::make('vat')->required()]),
                Step::make('Admin')
                    ->hidden(fn (Get $get): bool => $get('secret') !== 'admin')
                    ->schema([TextInput::make('code')->required()]),
            ]),
            TextInput::make('type'),
            TextInput::make('welcome'),
        ];
    }
}

beforeEach(function () {
    WizardTestForm::$calls = [];
    WizardTestForm::$allowed = true;
});

function stepKey(int $step): string
{
    return "data#1.{$step}";
}

function validateStep(array $data, int|string $step, ?string $token = null): TestResponse
{
    return test()->postJson(route('xelqun.forms.validate'), [
        'token' => $token ?? WizardTestForm::make()->makeToken(),
        'step' => is_int($step) ? stepKey($step) : $step,
        'state' => ['data' => $data],
        'deps' => (object) [],
    ]);
}

it('checks only the rules of the step, against the whole form', function () {
    validateStep(['name' => 'A', 'confirm_name' => 'B', 'plan' => null, 'items' => []], 0)
        ->assertOk()
        ->assertJsonPath('errors', [
            'data.name' => ['The Name field must be at least 2 characters.'],
            'data.confirm_name' => ['The Confirm Name field must match Name.'],
        ]);
});

it('checks the rows inside a step', function () {
    validateStep(['name' => 'Ada', 'confirm_name' => 'Ada', 'items' => ['r1' => ['qty' => null]]], 0)
        ->assertJsonPath('errors', ['data.items.r1.qty' => ['The Qty (Item 1) field is required.']]);
});

it('runs the hooks around the rules, and brings back what they set', function () {
    validateStep(['name' => 'Ada', 'confirm_name' => 'Ada', 'items' => []], 0)
        ->assertOk()
        ->assertJsonPath('errors', [])
        ->assertJsonPath('set', ['data.welcome' => 'Hello']);

    expect(WizardTestForm::$calls)->toBe(['before', 'after']);
});

it('skips the after hook when the step fails', function () {
    validateStep(['name' => null, 'items' => []], 0)->assertOk();

    expect(WizardTestForm::$calls)->toBe(['before']);
});

it('stops the step when a hook throws a validation error', function () {
    validateStep([], 2)->assertStatus(422)->assertJsonValidationErrors(['data.gate' => 'Not today.']);
});

it('refuses a key that is not a step', function (string $key) {
    validateStep([], $key)->assertNotFound();
})->with(['a field' => 'data.name', 'a wizard' => 'data#1', 'nothing' => 'data#99']);

it('follows visibleWhen on a step', function () {
    validateStep(['type' => 'person'], 3)->assertNotFound();

    expect(validateStep(['type' => 'business'], 3)->assertOk()->json('errors'))->toHaveKey('data.vat');
});

it('refuses a step the server hides, and a tampered field can not open it', function () {
    validateStep(['secret' => 'admin'], 4)->assertNotFound();
});

it('refuses a request for a form the user may not use', function () {
    $token = WizardTestForm::make()->makeToken();
    WizardTestForm::$allowed = false;

    validateStep([], 0, $token)->assertForbidden();
    expect(WizardTestForm::$calls)->toBe([]);
});

it('refuses a tampered token and a token of another user', function () {
    validateStep([], 0, 'nonsense')->assertForbidden();

    $token = WizardTestForm::make()->makeToken();
    $this->actingAs((new User)->forceFill(['id' => 5]));

    validateStep([], 0, $token)->assertForbidden();
    expect(WizardTestForm::$calls)->toBe([]);
});

it('checks every step on the final submit, whatever the wizard showed', function () {
    expect(fn () => WizardTestForm::make()->validate(['data' => ['name' => 'Ada', 'confirm_name' => 'Ada', 'items' => []]]))
        ->toThrow(ValidationException::class);
});

it('opens the step named in the query string', function () {
    $form = new class extends Form
    {
        public function schema(): array
        {
            return [Wizard::make([Step::make('Your account'), Step::make('Your plan')])->startOnStep(2)->persistStepInQueryString('page')];
        }
    };

    Route::get('/wizard', fn () => $form::make()->toSchema()['components'][0]['props']);

    $this->getJson('/wizard')->assertJsonPath('startKey', 'data#0.1');
    $this->getJson('/wizard?page=your-account')->assertJsonPath('startKey', 'data#0.0');
    $this->getJson('/wizard?page=nope')->assertJsonPath('startKey', 'data#0.1');
});

it('needs a form class, unless the wizard checks nothing', function () {
    expect(fn () => Form::make([Wizard::make([Step::make('One')])])->toSchema())->toThrow(FormDefinitionException::class);

    expect(Form::make([Wizard::make([Step::make('One')])->skippable()])->toSchema()['token'])->toBeNull();
});

describe('live validation', function () {
    function liveForm(): Form
    {
        return new class extends Form
        {
            public static int $hooks = 0;

            public function schema(): array
            {
                return [
                    TextInput::make('email')->email()->liveValidation(),
                    TextInput::make('name')->required()->live(),
                    TextInput::make('code')->disabled()->required()->liveValidation(),
                ];
            }
        };
    }

    it('defaults to checking on blur', function () {
        expect(liveForm()::make()->toSchema()['components'][0]['live'])->toBe(['mode' => 'blur', 'ms' => null]);
    });

    it('sends the errors of the field that changed, and only that one', function () {
        $patch = app(UpdateProcessor::class)->process(liveForm()::make(), ['email' => 'nope', 'name' => null], ['data.email'], [], 3);

        expect($patch['errors'])->toBe(['data.email' => ['The Email field must be a valid email address.']]);
    });

    it('sends no error for a valid value', function () {
        $patch = app(UpdateProcessor::class)->process(liveForm()::make(), ['email' => 'a@b.co', 'name' => null], ['data.email'], [], 3);

        expect((array) $patch['errors'])->toBe([]);
    });

    it('leaves a field alone unless it asks for this', function () {
        $patch = app(UpdateProcessor::class)->process(liveForm()::make(), ['name' => null], ['data.name'], [], 3);

        expect((array) $patch['errors'])->toBe([]);
    });

    it('does not check a field the user can not change', function () {
        $patch = app(UpdateProcessor::class)->process(liveForm()::make(), [], ['data.code'], [], 3);

        expect((array) $patch['errors'])->toBe([]);
    });
});

describe('frontend validation', function () {
    function clientRulesOf(object $field): ?array
    {
        $form = Form::make([$field])->frontendValidation();

        return $form->toSchema()['components'][0]['validation'] ?? null;
    }

    it('stays off by default', function () {
        expect(Form::make([TextInput::make('name')->required()])->toSchema()['components'][0])->not->toHaveKey('validation');
    });

    it('sends the typed rules, with messages that keep :attribute for the browser', function () {
        expect(clientRulesOf(TextInput::make('name')->required()->minLength(2)->maxLength(5)->validationMessages(['max' => 'Under :max, please.'])))->toBe([
            'attribute' => 'Name',
            'rules' => [
                ['rule' => 'required', 'message' => 'The :attribute field is required.'],
                ['rule' => 'min', 'type' => 'string', 'value' => 2, 'message' => 'The :attribute field must be at least 2 characters.'],
                ['rule' => 'max', 'type' => 'string', 'value' => 5, 'message' => 'Under 5, please.'],
            ],
        ]);
    });

    it('sends what numeric and email fields check', function () {
        expect(collect(clientRulesOf(TextInput::make('age')->integer()->minValue(18)->maxValue(99))['rules'])->pluck('rule')->all())->toBe(['integer', 'min', 'max'])
            ->and(collect(clientRulesOf(TextInput::make('mail')->email())['rules'])->pluck('rule')->all())->toBe(['email']);
    });

    it('uses accepted for a required checkbox', function () {
        expect(clientRulesOf(Checkbox::make('terms')->required())['rules'][0]['rule'])->toBe('accepted');
    });

    it('sends a rule for a field that is only sometimes required', function () {
        expect(clientRulesOf(TextInput::make('vat')->requiredWhen(When::eq('type', 'b')))['rules'][0]['rule'])->toBe('required');
    });

    it('never sends rules it can not prove mean the same thing', function () {
        expect(clientRulesOf(TextInput::make('name')->rules('required|regex:/x/')->rule(fn () => null)))->toBeNull()
            ->and(clientRulesOf(TextInput::make('name')->unique('users')->regex('/x/')->in(['a'])))->toBeNull();
    });

    it('skips what the server changes before it checks', function (object $field) {
        expect(clientRulesOf($field))->toBeNull();
    })->with([
        'stripped' => fn () => TextInput::make('price')->numeric()->stripCharacters(','),
        'trimmed' => fn () => TextInput::make('name')->maxLength(3)->trim(),
        'masked' => fn () => TextInput::make('phone')->integer()->mask('999-999'),
    ]);

    it('skips what the user can not fix or the server may fill in', function (object $field) {
        expect(clientRulesOf($field))->toBeNull();
    })->with([
        'hidden field' => fn () => Hidden::make('source')->required(),
        'rows' => fn () => Repeater::make('items')->required()->schema([TextInput::make('a')]),
    ]);

    it('covers a textarea', function () {
        expect(clientRulesOf(Textarea::make('bio')->maxLength(5))['rules'][0]['value'])->toBe(5);
    });
});
