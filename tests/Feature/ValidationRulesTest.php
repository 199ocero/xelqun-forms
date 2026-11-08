<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\Checkbox;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\Textarea;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Components\Toggle;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Group;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\Tests\Fixtures\Order;
use Xelqun\Forms\Tests\Fixtures\OrderForm;
use Xelqun\Forms\Tests\Fixtures\OrderNote;
use Xelqun\Forms\Tests\Fixtures\Plan;
use Xelqun\Forms\Tests\Fixtures\ScopedTag;
use Xelqun\Forms\Tests\Fixtures\Size;
use Xelqun\Forms\Tests\Fixtures\Tag;

/**
 * @param  array<string, mixed>  $data
 * @return array<string, array<int, string>>
 */
function errorsFor(Form $form, array $data): array
{
    try {
        $form->validate($data);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

/**
 * A form on the Order model, so unique() and exists() have a default.
 */
function orderFormWith(Closure $schema): OrderForm
{
    $form = new class extends OrderForm
    {
        public static Closure $schema;

        public function schema(): array
        {
            return (self::$schema)();
        }
    };
    $form::$schema = $schema;

    return $form;
}

function rowsSchema(Closure $field): Repeater
{
    return Repeater::make('items')->relationship()->schema([
        $field(TextInput::make('product')),
        TextInput::make('qty')->numeric()->default(1),
        TextInput::make('price')->numeric()->default(1),
        TextInput::make('subtotal')->numeric()->default(1),
    ]);
}

function itemRow(string $product): array
{
    return ['product' => $product, 'qty' => 1, 'price' => 1, 'subtotal' => 1];
}

it('checks each helper', function (Closure $configure, mixed $valid, mixed $invalid) {
    $make = fn (): Form => Form::make([$configure(TextInput::make('value'))]);

    expect(errorsFor($make(), ['value' => $valid]))->toBe([])
        ->and(errorsFor($make(), ['value' => $invalid]))->toHaveKey('data.value');
})->with([
    'alpha' => [fn (Field $field) => $field->alpha(), 'abc', 'ab1'],
    'alphaDash' => [fn (Field $field) => $field->alphaDash(), 'a-b', 'a b'],
    'alphaNum' => [fn (Field $field) => $field->alphaNum(), 'a1', 'a-1'],
    'ascii' => [fn (Field $field) => $field->ascii(), 'abc', 'é'],
    'hexColor' => [fn (Field $field) => $field->hexColor(), '#fff', 'red'],
    'ip' => [fn (Field $field) => $field->ip(), '10.0.0.1', 'nope'],
    'ipv4' => [fn (Field $field) => $field->ipv4(), '10.0.0.1', '::1'],
    'ipv6' => [fn (Field $field) => $field->ipv6(), '::1', '10.0.0.1'],
    'json' => [fn (Field $field) => $field->json(), '{}', '{'],
    'macAddress' => [fn (Field $field) => $field->macAddress(), '00:1B:44:11:3A:B7', 'nope'],
    'uuid' => [fn (Field $field) => $field->uuid(), '9d7e6f5a-1b2c-4d3e-8f90-a1b2c3d4e5f6', 'nope'],
    'ulid' => [fn (Field $field) => $field->ulid(), '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'nope'],
    'startsWith' => [fn (Field $field) => $field->startsWith(['a', 'x,y']), 'x,y1', 'b'],
    'endsWith' => [fn (Field $field) => $field->endsWith(['a', 'x,y']), '1x,y', 'b'],
    'doesntStartWith' => [fn (Field $field) => $field->doesntStartWith(['a']), 'b', 'ab'],
    'doesntEndWith' => [fn (Field $field) => $field->doesntEndWith(['a']), 'b', 'ba'],
    'in' => [fn (Field $field) => $field->in(['a', 'x,y']), 'x,y', 'x'],
    'in from a closure' => [fn (Field $field) => $field->in(fn (): array => ['a']), 'a', 'b'],
    'notIn' => [fn (Field $field) => $field->notIn(['a']), 'b', 'a'],
    'enum' => [fn (Field $field) => $field->enum(Plan::class), 'pro', 'gold'],
    'regex with a pipe' => [fn (Field $field) => $field->regex('/^(a|b)$/'), 'b', 'c'],
    'notRegex with a pipe' => [fn (Field $field) => $field->notRegex('/^(a|b)$/'), 'c', 'b'],
    'multipleOf' => [fn (Field $field) => $field->numeric()->multipleOf(5), 10, 7],
    'after a date' => [fn (Field $field) => $field->after('2026-01-01'), '2026-06-01', '2025-06-01'],
    'afterOrEqual a date' => [fn (Field $field) => $field->afterOrEqual('2026-01-01'), '2026-01-01', '2025-12-31'],
    'before a date' => [fn (Field $field) => $field->before('2026-01-01'), '2025-06-01', '2026-06-01'],
    'beforeOrEqual a date' => [fn (Field $field) => $field->beforeOrEqual('2026-01-01'), '2026-01-01', '2026-01-02'],
    'prohibited' => [fn (Field $field) => $field->prohibited(), null, 'x'],
    'filled' => [fn (Field $field) => $field->filled(), 'x', ''],
    'string' => [fn (Field $field) => $field->string(), 'x', ['x']],
    'rule, while its condition holds' => [fn (Field $field) => $field->rule('alpha', fn (): bool => true), 'a', '1'],
]);

it('skips a helper whose condition is off', function () {
    $form = Form::make([TextInput::make('value')->alpha(false)->rule('uuid', fn (): bool => false)]);

    expect(errorsFor($form, ['value' => '1']))->toBe([]);
});

describe('rules that name another field', function () {
    it('compares against the other field', function (Closure $configure, array $valid, array $invalid) {
        $make = fn (): Form => Form::make([
            TextInput::make('other')->numeric(),
            TextInput::make('text'),
            $configure(TextInput::make('value')->numeric()),
        ]);

        expect(errorsFor($make(), $valid))->toBe([])
            ->and(errorsFor($make(), $invalid))->toHaveKey('data.value');
    })->with([
        'gt' => [fn (Field $field) => $field->gt('other'), ['other' => 1, 'value' => 2], ['other' => 2, 'value' => 2]],
        'gte' => [fn (Field $field) => $field->gte('other'), ['other' => 2, 'value' => 2], ['other' => 3, 'value' => 2]],
        'lt' => [fn (Field $field) => $field->lt('other'), ['other' => 3, 'value' => 2], ['other' => 2, 'value' => 2]],
        'lte' => [fn (Field $field) => $field->lte('other'), ['other' => 2, 'value' => 2], ['other' => 1, 'value' => 2]],
        'same' => [fn (Field $field) => $field->same('other'), ['other' => 2, 'value' => 2], ['other' => 3, 'value' => 2]],
        'different' => [fn (Field $field) => $field->different('other'), ['other' => 3, 'value' => 2], ['other' => 2, 'value' => 2]],
        'requiredIf, with a comma' => [fn (Field $field) => $field->requiredIf('text', 'a,b'), ['text' => 'a', 'value' => null], ['text' => 'a,b', 'value' => null]],
        'requiredUnless' => [fn (Field $field) => $field->requiredUnless('text', 'a'), ['text' => 'a', 'value' => null], ['text' => 'b', 'value' => null]],
        'requiredWith' => [fn (Field $field) => $field->requiredWith('text'), ['value' => null], ['text' => 'a', 'value' => null]],
        'requiredWithAll' => [fn (Field $field) => $field->requiredWithAll('text, other'), ['text' => 'a', 'value' => null], ['text' => 'a', 'other' => 1, 'value' => null]],
        'requiredWithout' => [fn (Field $field) => $field->requiredWithout(['text']), ['text' => 'a', 'value' => null], ['value' => null]],
        'requiredWithoutAll' => [fn (Field $field) => $field->requiredWithoutAll(['text', 'other']), ['text' => 'a', 'value' => null], ['value' => null]],
        'requiredIfAccepted' => [fn (Field $field) => $field->requiredIfAccepted('text'), ['text' => 'no', 'value' => null], ['text' => 'yes', 'value' => null]],
        'prohibitedIf' => [fn (Field $field) => $field->prohibitedIf('text', 'a'), ['text' => 'b', 'value' => 1], ['text' => 'a', 'value' => 1]],
        'prohibitedUnless' => [fn (Field $field) => $field->prohibitedUnless('text', 'a'), ['text' => 'a', 'value' => 1], ['text' => 'b', 'value' => 1]],
        'prohibits' => [fn (Field $field) => $field->prohibits('text'), ['value' => 1], ['text' => 'a', 'value' => 1]],
    ]);

    it('matches true and false against a checkbox or toggle', function (Closure $field) {
        $make = fn (mixed $value): Form => Form::make([
            $field(),
            TextInput::make('note')->requiredIf('flag', $value),
        ]);

        expect(errorsFor($make(true), ['flag' => true, 'note' => null]))->toHaveKey('data.note')
            ->and(errorsFor($make(true), ['flag' => false, 'note' => null]))->toBe([])
            ->and(errorsFor($make(false), ['flag' => false, 'note' => null]))->toHaveKey('data.note')
            ->and(errorsFor($make(false), ['flag' => true, 'note' => null]))->toBe([]);
    })->with([
        'checkbox' => [fn (): Field => Checkbox::make('flag')],
        'toggle' => [fn (): Field => Toggle::make('flag')],
    ]);

    it('treats true the same in the other rules that take a value', function () {
        $form = Form::make([
            Checkbox::make('flag'),
            TextInput::make('required')->requiredUnless('flag', true),
            TextInput::make('banned')->prohibitedIf('flag', true),
        ]);

        expect(errorsFor($form, ['flag' => true, 'required' => null, 'banned' => 'x']))
            ->toHaveKey('data.banned')->not->toHaveKey('data.required');
    });

    it('matches an enum case and null', function () {
        $backed = fn (): Form => Form::make([TextInput::make('plan'), TextInput::make('note')->requiredIf('plan', Plan::Pro)]);
        $pure = fn (): Form => Form::make([TextInput::make('size'), TextInput::make('note')->requiredIf('size', Size::Large)]);
        $empty = fn (): Form => Form::make([TextInput::make('plan'), TextInput::make('note')->requiredIf('plan', null)]);

        expect(errorsFor($backed(), ['plan' => 'pro', 'note' => null]))->toHaveKey('data.note')
            ->and(errorsFor($backed(), ['plan' => 'free', 'note' => null]))->toBe([])
            ->and(errorsFor($pure(), ['size' => 'Large', 'note' => null]))->toHaveKey('data.note')
            ->and(errorsFor($empty(), ['plan' => null, 'note' => null]))->toHaveKey('data.note')
            ->and(errorsFor($empty(), ['plan' => 'pro', 'note' => null]))->toBe([]);
    });

    it('takes a number as a number, not as a field', function (Closure $configure, mixed $valid, mixed $invalid) {
        $make = fn (): Form => Form::make([$configure(TextInput::make('value')->numeric())]);

        expect(errorsFor($make(), ['value' => $valid]))->toBe([])
            ->and(errorsFor($make(), ['value' => $invalid]))->toHaveKey('data.value');
    })->with([
        'gt' => [fn (Field $field) => $field->gt('0'), 5, 0],
        'gte' => [fn (Field $field) => $field->gte(1), 1, 0],
        'lt' => [fn (Field $field) => $field->lt('10.5'), 10, 11],
        'lte' => [fn (Field $field) => $field->lte('10'), 10, 11],
    ]);

    it('skips a date rule when its closure gives no date', function () {
        $make = fn (): Form => Form::make([
            TextInput::make('starts'),
            TextInput::make('ends')
                ->after(fn (Get $get): ?string => $get('starts'))
                ->beforeOrEqual(fn (Get $get): ?string => $get('starts') ?: ''),
        ]);

        expect(errorsFor($make(), ['starts' => null, 'ends' => '2026-01-01']))->toBe([])
            ->and(errorsFor($make(), ['starts' => '2026-06-01', 'ends' => '2026-01-01']))->toHaveKey('data.ends');
    });

    it('compares a date with another field', function () {
        $make = fn (): Form => Form::make([
            TextInput::make('starts'),
            TextInput::make('ends')->after('starts'),
        ]);

        expect(errorsFor($make(), ['starts' => '2026-01-01', 'ends' => '2026-02-01']))->toBe([])
            ->and(errorsFor($make(), ['starts' => '2026-03-01', 'ends' => '2026-02-01']))->toHaveKey('data.ends');
    });

    it('reaches a field named like a date with $.', function () {
        $make = fn (): Form => Form::make([
            TextInput::make('today'),
            TextInput::make('ends')->after('$.today'),
        ]);

        expect(errorsFor($make(), ['today' => '2030-03-01', 'ends' => '2030-02-01']))->toHaveKey('data.ends');
    });

    it('compares inside its own row, or with the form using $.', function () {
        $make = fn (): Form => Form::make([
            TextInput::make('limit')->numeric(),
            Repeater::make('rows')->schema([
                TextInput::make('low')->numeric(),
                TextInput::make('high')->numeric()->gt('low')->lt('$.limit'),
            ]),
        ]);
        $data = ['limit' => 10, 'rows' => ['a' => ['low' => 1, 'high' => 2], 'b' => ['low' => 5, 'high' => 3], 'c' => ['low' => 1, 'high' => 11]]];

        expect(errorsFor($make(), $data))->toHaveKeys(['data.rows.b.high', 'data.rows.c.high'])
            ->not->toHaveKey('data.rows.a.high');
    });

    it('compares a date with a field in its own row', function () {
        $form = Form::make([
            Repeater::make('rows')->schema([
                TextInput::make('starts'),
                TextInput::make('ends')->after('starts'),
            ]),
        ]);

        expect(errorsFor($form, ['rows' => [
            'a' => ['starts' => '2026-01-01', 'ends' => '2026-02-01'],
            'b' => ['starts' => '2026-03-01', 'ends' => '2026-02-01'],
        ]]))->toHaveKey('data.rows.b.ends')->not->toHaveKey('data.rows.a.ends');
    });

    it('confirms a value inside a row', function () {
        $make = fn (): Form => Form::make([
            Repeater::make('rows')->schema([
                TextInput::make('pin')->confirmed(),
                TextInput::make('pin_confirmation')->saved(false),
            ]),
        ]);

        expect(errorsFor($make(), ['rows' => ['a' => ['pin' => '1', 'pin_confirmation' => '1']]]))->toBe([])
            ->and(errorsFor($make(), ['rows' => ['a' => ['pin' => '1', 'pin_confirmation' => '2']]]))->toHaveKey('data.rows.a.pin');
    });

    it('names the other field by its label in the message', function () {
        $form = Form::make([
            TextInput::make('first')->label('First code'),
            TextInput::make('second')->same('first'),
        ]);

        expect(errorsFor($form, ['first' => 'a', 'second' => 'b'])['data.second'][0])->toContain('First code');
    });

    it('evaluates a rule condition against its own row', function () {
        $form = Form::make([
            Repeater::make('rows')->schema([
                TextInput::make('kind'),
                TextInput::make('code')->rule('alpha', fn (Get $get): bool => $get('kind') === 'letters'),
            ]),
        ]);

        expect(errorsFor($form, ['rows' => [
            'a' => ['kind' => 'letters', 'code' => '1'],
            'b' => ['kind' => 'digits', 'code' => '1'],
        ]]))->toHaveKey('data.rows.a.code')->not->toHaveKey('data.rows.b.code');
    });
});

describe('unique', function () {
    beforeEach(function () {
        $this->taken = Order::query()->create(['customer' => 'Ada']);
    });

    it('defaults to the model and the field name', function () {
        $form = orderFormWith(fn () => [TextInput::make('customer')->unique()]);

        expect(errorsFor($form::make(), ['customer' => 'Ada']))->toHaveKey('data.customer')
            ->and(errorsFor($form::make(), ['customer' => 'Grace']))->toBe([]);
    });

    it('ignores the record being edited unless told not to', function () {
        $form = orderFormWith(fn () => [TextInput::make('customer')->unique()]);

        expect(errorsFor($form::make($this->taken), ['customer' => 'Ada']))->toBe([]);

        $form = orderFormWith(fn () => [TextInput::make('customer')->unique(ignoreRecord: false)]);

        expect(errorsFor($form::make($this->taken), ['customer' => 'Ada']))->toHaveKey('data.customer');
    });

    it('takes a table, a column, an ignored record and a rule change', function () {
        $byTable = Form::make([TextInput::make('name')->unique('orders', 'customer')]);
        $byClass = Form::make([TextInput::make('name')->unique(Order::class, 'customer')]);
        $ignoring = Form::make([TextInput::make('name')->unique('orders', 'customer', ignorable: $this->taken)]);
        $changed = Form::make([TextInput::make('name')->unique('orders', 'customer', modifyRuleUsing: fn (Unique $rule) => $rule->where('type', 'business'))]);

        expect(errorsFor($byTable, ['name' => 'Ada']))->toHaveKey('data.name')
            ->and(errorsFor($byClass, ['name' => 'Ada']))->toHaveKey('data.name')
            ->and(errorsFor($ignoring, ['name' => 'Ada']))->toBe([])
            ->and(errorsFor($changed, ['name' => 'Ada']))->toBe([]);
    });

    it('leaves the record alone when checking another table', function () {
        $tag = Tag::query()->create(['id' => $this->taken->id, 'name' => 'taken']);

        expect($tag->id)->toBe($this->taken->id);

        $form = orderFormWith(fn () => [TextInput::make('label')->unique('tags', 'name')]);

        expect(errorsFor($form::make($this->taken), ['label' => 'taken']))->toHaveKey('data.label');

        $form = orderFormWith(fn () => [TextInput::make('label')->unique(Tag::class, 'name')]);

        expect(errorsFor($form::make($this->taken), ['label' => 'taken']))->toHaveKey('data.label');

        $form = orderFormWith(fn () => [TextInput::make('label')->scopedUnique(Tag::class, 'name')]);

        expect(errorsFor($form::make($this->taken), ['label' => 'taken']))->toHaveKey('data.label');
    });

    it('still ignores the record however its own table is named', function (Closure $table) {
        $form = orderFormWith(fn () => [TextInput::make('customer')->unique($table(), 'customer')]);

        expect(errorsFor($form::make($this->taken), ['customer' => 'Ada']))->toBe([]);
    })->with([
        'table' => [fn (): string => 'orders'],
        'model' => [fn (): string => Order::class],
        'connection and table' => [fn (): string => config('database.default').'.orders'],
    ]);

    it('needs a table when the form has no model', function () {
        expect(fn () => Form::make([TextInput::make('name')->unique()])->validate(['name' => 'x']))
            ->toThrow(FormDefinitionException::class);
    });

    it('survives a posted array', function () {
        $form = orderFormWith(fn () => [TextInput::make('customer')->unique()]);

        expect(errorsFor($form::make(), ['customer' => ['Ada', 'Grace']]))->toHaveKey('data.customer');
    });

    it('in a related row, ignores only a row of this record', function () {
        $form = orderFormWith(fn () => [rowsSchema(fn (Field $field) => $field->unique())]);
        $own = $this->taken->items()->create(itemRow('Desk'));
        $other = Order::query()->create(['customer' => 'Grace'])->items()->create(itemRow('Lamp'));
        $key = Repeater::RECORD_KEY_PREFIX;

        expect(errorsFor($form::make($this->taken), ['items' => [$key.$own->id => itemRow('Desk')]]))->toBe([])
            ->and(errorsFor($form::make($this->taken), ['items' => [$key.$own->id => itemRow('Lamp')]]))->toHaveKey("data.items.{$key}{$own->id}.product")
            ->and(errorsFor($form::make($this->taken), ['items' => ['new' => itemRow('Desk')]]))->toHaveKey('data.items.new.product');

        // A forged key must not make the other order's row look like this form's own.
        expect(errorsFor($form::make($this->taken), ['items' => [$key.$other->id => itemRow('Lamp')]]))
            ->toHaveKey("data.items.{$key}{$other->id}.product");
    });

    it('in a related group, ignores the related record', function () {
        $form = orderFormWith(fn () => [Group::make([TextInput::make('body')->unique()])->relationship('note')]);
        $this->taken->note()->create(['body' => 'Hi']);

        expect(errorsFor($form::make($this->taken), ['note' => ['body' => 'Hi']]))->toBe([])
            ->and(errorsFor($form::make(), ['note' => ['body' => 'Hi']]))->toHaveKey('data.note.body')
            ->and(OrderNote::query()->count())->toBe(1);
    });
});

describe('scoped rules', function () {
    beforeEach(function () {
        $this->public = Tag::query()->create(['name' => 'php']);
        $this->staff = Tag::query()->create(['name' => 'internal', 'group' => 'staff']);
    });

    it('scopedUnique respects global scopes, and can drop them', function () {
        $scoped = Form::make([TextInput::make('name')->scopedUnique(ScopedTag::class)]);
        $unscoped = Form::make([TextInput::make('name')->scopedUnique(ScopedTag::class, modifyQueryUsing: fn (Builder $query) => $query->withoutGlobalScopes())]);

        expect(errorsFor($scoped, ['name' => 'php']))->toHaveKey('data.name')
            ->and(errorsFor($scoped, ['name' => 'internal']))->toBe([])
            ->and(errorsFor($unscoped, ['name' => 'internal']))->toHaveKey('data.name');
    });

    it('scopedUnique ignores the record being edited', function () {
        $order = Order::query()->create(['customer' => 'Ada']);
        $form = orderFormWith(fn () => [TextInput::make('customer')->scopedUnique()]);

        expect(errorsFor($form::make($order), ['customer' => 'Ada']))->toBe([])
            ->and(errorsFor($form::make(), ['customer' => 'Ada']))->toHaveKey('data.customer');
    });

    it('exists takes a table, a column and a rule change', function () {
        $form = fn (?Closure $modify = null): Form => Form::make([TextInput::make('tag')->exists('tags', 'name', $modify)]);

        expect(errorsFor($form(), ['tag' => 'php']))->toBe([])
            ->and(errorsFor($form(), ['tag' => 'nope']))->toHaveKey('data.tag')
            ->and(errorsFor($form(fn (Exists $rule) => $rule->where('group', 'staff')), ['tag' => 'php']))->toHaveKey('data.tag');
    });

    it('scopedExists respects global scopes and checks every value', function () {
        $single = fn (): Form => Form::make([TextInput::make('name')->scopedExists(ScopedTag::class)]);
        $many = fn (): Form => Form::make([Select::make('name')->multiple()->options(['php' => 'p', 'internal' => 'i'])->scopedExists(ScopedTag::class)]);

        expect(errorsFor($single(), ['name' => 'php']))->toBe([])
            ->and(errorsFor($single(), ['name' => 'internal']))->toHaveKey('data.name')
            ->and(errorsFor($many(), ['name' => ['php']]))->toBe([])
            ->and(errorsFor($many(), ['name' => ['php', 'internal']]))->toHaveKey('data.name');
    });
});

describe('messages and attributes', function () {
    it('uses a custom message for a rule', function () {
        $form = Form::make([TextInput::make('name')->required()->validationMessages(['required' => 'Who is this for?'])]);

        expect(errorsFor($form, ['name' => null])['data.name'])->toBe(['Who is this for?']);
    });

    it('fills in placeholders and evaluates closures', function () {
        $form = Form::make([
            TextInput::make('code')->label('Code')->minLength(3)->validationMessages([
                'min' => fn (Get $get): string => ':attribute needs :min, got '.$get('code'),
            ]),
        ]);

        expect(errorsFor($form, ['code' => 'a'])['data.code'])->toBe(['Code needs 3, got a']);
    });

    it('applies to every row, with the row in the attribute', function () {
        $form = Form::make([
            Repeater::make('rows')->itemLabel('Guest')->schema([
                TextInput::make('name')->required()->validationMessages(['required' => ':attribute is missing.']),
            ]),
        ]);

        expect(errorsFor($form, ['rows' => ['a' => ['name' => 'x'], 'b' => ['name' => null]]])['data.rows.b.name'])
            ->toBe(['Name (Guest 2) is missing.']);
    });

    it('reaches rules that check with a closure', function (Closure $field, array $data) {
        expect(errorsFor(orderFormWith(fn () => [$field()])::make(), $data))->toBe(['data.pick' => ['Not that one.']]);
    })->with([
        'select in' => [fn (): Field => Select::make('pick')->multiple()->options(['a' => 'A'])->validationMessages(['in' => 'Not that one.']), ['pick' => ['b']]],
        'relationship exists' => [fn (): Field => Select::make('pick')->relationship('country', 'name')->validationMessages(['exists' => 'Not that one.']), ['pick' => 999]],
        'upload path' => [fn (): Field => FileUpload::make('pick')->validationMessages(['in' => 'Not that one.']), ['pick' => 'orders/stolen.pdf']],
        'async select' => [fn (): Field => Select::make('pick')->getSearchResultsUsing(fn (): array => [])->getOptionLabelUsing(fn (): ?string => null)->validationMessages(['in' => 'Not that one.']), ['pick' => 'x']],
        'scopedUnique' => [function (): Field {
            Tag::query()->create(['name' => 'x']);

            return TextInput::make('pick')->scopedUnique(Tag::class, 'name')->validationMessages(['unique' => 'Not that one.']);
        }, ['pick' => 'x']],
    ]);

    it('names the field with validationAttribute, even in a closure rule', function () {
        $form = Form::make([
            TextInput::make('name')->required()->validationAttribute('full name'),
            Select::make('plan')->options(['a' => 'A'])->label('Plan')->validationAttribute('your plan'),
        ]);

        expect(errorsFor($form, ['name' => null, 'plan' => 'b']))->toBe([
            'data.name' => ['The full name field is required.'],
            'data.plan' => ['The selected your plan is invalid.'],
        ]);
    });

    it('keeps the row in a closure rule\'s attribute', function () {
        $form = orderFormWith(fn () => [Repeater::make('rows')->itemLabel('Guest')->schema([
            Select::make('pick')->relationship('country', 'name'),
        ])]);

        expect(errorsFor($form::make(), ['rows' => ['a' => ['pick' => 999]]])['data.rows.a.pick'][0])->toContain('Pick (Guest 1)');
    });

    it('uses it for the upload endpoint', function () {
        Storage::fake('local');

        $form = orderFormWith(fn () => [FileUpload::make('pick')->maxSize(1)->validationAttribute('your receipt')]);

        $this->uploadToForm($form::make(), 'data.pick', UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'your receipt']);
    });
});

describe('a field that is not saved', function () {
    it('still runs its rules', function (Closure $field, array $data) {
        expect(errorsFor(Form::make([TextInput::make('password'), $field()]), $data))->toHaveKey('data.check');
    })->with([
        'same with saved(false)' => [fn (): Field => TextInput::make('check')->same('password')->saved(false), ['password' => 'a', 'check' => 'b']],
        'required with saved(false)' => [fn (): Field => TextInput::make('check')->required()->saved(false), ['check' => null]],
        'required with dehydrated(false)' => [fn (): Field => TextInput::make('check')->required()->dehydrated(false), ['check' => null]],
        'a closure that turns saving off' => [fn (): Field => TextInput::make('check')->required()->saved(fn (): bool => false), ['check' => null]],
    ]);

    it('stays out of the output once it passes', function () {
        $form = Form::make([
            TextInput::make('password'),
            TextInput::make('check')->same('password')->saved(false),
        ]);

        expect($form->validate(['password' => 'a', 'check' => 'a']))->toBe(['password' => 'a']);
    });

    it('validates the rows of a repeater but keeps none of them', function () {
        $form = fn (): Form => Form::make([
            Repeater::make('rows')->saved(false)->schema([TextInput::make('name')->required()]),
        ]);

        expect(errorsFor($form(), ['rows' => ['a' => ['name' => null]]]))->toHaveKey('data.rows.a.name')
            ->and($form()->validate(['rows' => ['a' => ['name' => 'x']]]))->toBe([]);
    });

    it('never reaches a related table', function () {
        $form = orderFormWith(fn () => [
            TextInput::make('customer'),
            Repeater::make('items')->relationship()->saved(false)->schema([
                TextInput::make('product')->required(),
                TextInput::make('qty')->numeric()->default(1),
                TextInput::make('price')->numeric()->default(1),
                TextInput::make('subtotal')->numeric()->default(1),
            ]),
        ]);

        $order = $form::make()->save(['customer' => 'Ada', 'items' => ['a' => itemRow('Desk')]]);

        expect($order->items()->count())->toBe(0);
    });

    it('does not validate a locked field, saved or not', function () {
        $form = fn (Closure $configure): Form => Form::make([$configure(TextInput::make('code')->default('1')->alpha()->disabled())]);

        expect(errorsFor($form(fn (Field $field) => $field), ['code' => '2']))->toBe([])
            ->and(errorsFor($form(fn (Field $field) => $field->saved(false)), ['code' => '2']))->toBe([]);
    });

    it('leaves a read-only repeater on an existing record unwritten', function () {
        $order = Order::query()->create(['customer' => 'Ada']);
        $item = $order->items()->create(itemRow('Desk'));
        $form = orderFormWith(fn () => [
            TextInput::make('customer'),
            rowsSchema(fn (Field $field) => $field->required())->readOnly(),
        ]);

        $form::make($order)->save(['customer' => 'Ada', 'items' => [Repeater::RECORD_KEY_PREFIX.$item->id => itemRow('Tampered')]]);

        expect($item->fresh()->product)->toBe('Desk');
    });
});

it('skips helpers on a locked field and on a read-only one', function () {
    $form = Form::make([
        TextInput::make('code')->default('1')->alpha()->disabled(),
        Textarea::make('note')->default('1')->alpha()->readOnly(),
    ]);

    expect(errorsFor($form, ['code' => '2', 'note' => '2']))->toBe([]);
});

it('adds helpers to a repeater', function () {
    $form = Form::make([
        TextInput::make('extra'),
        Repeater::make('rows')->requiredWith('extra')->schema([TextInput::make('name')]),
    ]);

    expect(errorsFor($form, ['extra' => 'x', 'rows' => []]))->toHaveKey('data.rows');
});

it('runs a rule built by a closure', function () {
    $form = Form::make([
        TextInput::make('slug')->rules([
            fn () => function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === 'admin') {
                    $fail('That name is taken.');
                }
            },
        ]),
        TextInput::make('code')->rules([fn (Get $get): string => $get('slug') === 'strict' ? 'min:5|max:9' : 'min:1']),
    ]);

    expect(errorsFor($form, ['slug' => 'admin', 'code' => 'ab']))->toBe(['data.slug' => ['That name is taken.']])
        ->and(errorsFor($form, ['slug' => 'strict', 'code' => 'ab']))->toHaveKey('data.code');
});
