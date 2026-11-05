<?php

use Illuminate\Support\Str;
use Xelqun\Forms\Components\Checkbox;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Conditions\When;
use Xelqun\Forms\Form;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\State\Set;
use Xelqun\Forms\Tests\Fixtures\OperationsForm;
use Xelqun\Forms\Tests\Fixtures\Order;

function existingOrder(): Order
{
    return Order::query()->create(['customer' => 'ada', 'type' => 'business', 'company' => 'Lovelace', 'total' => 40]);
}

/**
 * @return array<string, mixed>
 */
function nodeOf(Form $form, string $name): array
{
    return collect($form->toSchema()['components'])->firstWhere('path', "data.{$name}");
}

function staticForm(Field $field): Form
{
    return Form::make([$field]);
}

describe('operation', function () {
    it('comes from the record and the context', function () {
        expect(OperationsForm::make()->getOperation())->toBe('create')
            ->and(OperationsForm::make(new Order)->getOperation())->toBe('create')
            ->and(OperationsForm::make(existingOrder())->getOperation())->toBe('edit')
            ->and(OperationsForm::make(existingOrder(), ['operation' => 'view'])->getOperation())->toBe('view');
    });

    it('stays the same after a new record is saved', function () {
        $form = OperationsForm::make();
        $form->save(['customer' => 'Ada', 'items' => []]);

        expect($form->getRecord()->exists)->toBeTrue()
            ->and($form->getOperation())->toBe('create');
    });

    it('survives the token and ignores anything posted', function () {
        $encrypter = app(FormTokenEncrypter::class);
        $order = existingOrder();

        expect($encrypter->resolveForm(OperationsForm::make($order, ['operation' => 'view'])->makeToken())->getOperation())->toBe('view')
            ->and($encrypter->resolveForm(OperationsForm::make($order)->makeToken())->getOperation())->toBe('edit');

        $form = OperationsForm::make($order);
        $form->validate(['operation' => 'create', 'customer' => 'Ada', 'items' => []]);

        expect($form->getOperation())->toBe('edit');
    });

    it('is injected into closures', function () {
        expect(OperationsForm::make()->getData()['operation_label'])->toBe('on create')
            ->and(OperationsForm::make(existingOrder())->getData()['operation_label'])->toBe('on edit');
    });
});

describe('operation rules', function () {
    it('shows different fields on create and edit', function () {
        $paths = fn (Form $form): array => array_column($form->toSchema()['components'], 'path');

        expect($paths(OperationsForm::make()))->toContain('data.company', 'data.confirmation')
            ->and($paths(OperationsForm::make(existingOrder())))->not->toContain('data.company', 'data.confirmation')
            ->and(nodeOf(OperationsForm::make(), 'type')['disabled'])->toBeFalse()
            ->and(nodeOf(OperationsForm::make(existingOrder()), 'type')['disabled'])->toBeTrue();
    });

    it('never sends a field hidden on this operation to the browser', function () {
        expect(OperationsForm::make(existingOrder())->toSchema()['state']['data'])->not->toHaveKey('company');
    });

    it('keeps tampered values out of edits and away from closures', function () {
        $order = existingOrder();
        $form = OperationsForm::make($order);
        OperationsForm::$reads = [];

        $form->save(['customer' => 'Grace', 'type' => 'hacked', 'company' => 'hacked', 'total' => 999, 'items' => []]);

        expect($order->fresh())
            ->customer->toBe('grace')
            ->type->toBe('business')
            ->company->toBe('Lovelace')
            ->and((float) $order->fresh()->total)->toBe(40.0)
            ->and(OperationsForm::$reads)->not->toBeEmpty()->not->toContain('hacked')->not->toContain(999);
    });

    it('replaces the opposite setting, whichever came last', function (Closure $configure, bool $hidden) {
        expect($configure(TextInput::make('name'))->mount(OperationsForm::make(), null, 'data', '0')->isHiddenStatically())->toBe($hidden);
    })->with([
        'hiddenOn after hidden' => [fn (Component $field) => $field->hidden()->hiddenOn('edit'), false],
        'hidden after hiddenOn' => [fn (Component $field) => $field->hiddenOn('create')->hidden(false), false],
        'visible after visibleOn' => [fn (Component $field) => $field->visibleOn('edit')->visible(), false],
        'visibleOn after visible' => [fn (Component $field) => $field->visible()->visibleOn('edit'), true],
    ]);

    it('takes a list of operations', function () {
        expect(TextInput::make('name')->hiddenOn(['edit', 'create'])->mount(OperationsForm::make(), null, 'data', '0')->isHiddenStatically())->toBeTrue();
    });
});

describe('readOnly', function () {
    it('writes its default on create, whatever was posted, and skips the rules', function () {
        $order = OperationsForm::make()->save(['customer' => 'Ada', 'total' => 999, 'items' => []]);

        expect((float) $order->total)->toBe(7.0);
    });

    it('keeps a row value on both new and stored rows', function () {
        $order = OperationsForm::make()->save(['customer' => 'Ada', 'items' => ['r1' => ['product' => 'Desk', 'qty' => 1, 'price' => 2, 'subtotal' => 999]]]);
        $item = $order->items()->sole();

        expect((float) $item->subtotal)->toBe(3.0);

        OperationsForm::make($order->fresh())->save(['customer' => 'Ada', 'items' => [
            Repeater::RECORD_KEY_PREFIX.$item->id => ['product' => 'Lamp', 'qty' => 1, 'price' => 2, 'subtotal' => 999],
        ]]);

        expect($item->fresh())->product->toBe('Lamp')
            ->and((float) $item->fresh()->subtotal)->toBe(3.0);
    });

    it('writes the default of a row added while editing', function () {
        $order = existingOrder();

        OperationsForm::make($order)->save(['customer' => 'Ada', 'items' => [
            'r1' => ['product' => 'Desk', 'qty' => 1, 'price' => 2, 'subtotal' => 999],
        ]]);

        expect((float) $order->items()->sole()->subtotal)->toBe(3.0);
    });

    it('keeps its value in a row with no model, which is stored whole', function () {
        $form = Form::make([
            Repeater::make('rows')->schema([
                TextInput::make('sku')->default('locked')->readOnly(),
                TextInput::make('qty')->numeric(),
            ]),
        ]);

        expect($form->validate(['rows' => ['r1' => ['sku' => 'tampered', 'qty' => 2]]])['rows'])
            ->toBe([['sku' => 'locked', 'qty' => 2]]);
    });

    it('never writes a formatted value over a stored related row', function () {
        $order = existingOrder();
        $item = $order->items()->create(['product' => 'Desk', 'qty' => 1, 'price' => 2, 'subtotal' => 3, 'attachment' => 'spec.pdf']);
        $form = OperationsForm::make($order->fresh());

        expect($form->getData()['items'][Repeater::RECORD_KEY_PREFIX.$item->id]['attachment'])->toBe('SPEC.PDF');

        $form->save(['customer' => 'Ada', 'items' => [
            Repeater::RECORD_KEY_PREFIX.$item->id => ['product' => 'Desk', 'qty' => 1, 'price' => 2, 'attachment' => 'tampered'],
        ]]);

        expect($item->fresh()->attachment)->toBe('spec.pdf');
    });

    it('keeps a stored value in a row with no model on edit', function () {
        $order = existingOrder()->setAttribute('rows', [['sku' => 'stored', 'qty' => 1]]);
        $form = new class($order) extends Form
        {
            public function schema(): array
            {
                return [
                    Repeater::make('rows')->schema([
                        TextInput::make('sku')->readOnly(),
                        TextInput::make('qty')->numeric(),
                    ]),
                ];
            }
        };
        $rowKey = array_key_first($form->fill()->getData()['rows']);

        expect($form->validate(['rows' => [$rowKey => ['sku' => 'tampered', 'qty' => 2]]])['rows'])
            ->toBe([['sku' => 'stored', 'qty' => 2]]);
    });

    it('locks the rows of a read-only repeater', function () {
        $form = Form::make([
            Repeater::make('rows')->readOnly()->defaultItems(1)->schema([TextInput::make('sku')->default('server')->required()]),
        ]);
        $repeater = $form->toSchema()['components'][0];
        $rows = (array) ((array) $repeater['props'])['rows'];

        expect($rows['row-0'][0]['readOnly'])->toBeTrue()
            ->and($form->validate(['rows' => ['row-0' => ['sku' => 'tampered']]])['rows'])->toBe([['sku' => 'server']]);

        $required = Form::make([
            Repeater::make('rows')->readOnly()->defaultItems(1)->schema([TextInput::make('sku')->required()]),
        ]);

        expect($required->validate(['rows' => ['row-0' => ['sku' => 'typed']]])['rows'])->toBe([['sku' => null]]);
    });

    it('leaves a computed field calculating', function () {
        $data = staticForm(TextInput::make('total')->numeric()->computed('1 + 1')->readOnly())->validate(['total' => 999]);

        expect($data['total'])->toBe(2);
    });

    it('refuses the field endpoints', function () {
        expect(TextInput::make('name')->readOnly()->mount(OperationsForm::make(), null, 'data', '0')->acceptsInput())->toBeFalse();

        $form = OperationsForm::make();
        $response = $this->updateForm($form, ['data.reference'], ['customer' => 'Ada', 'reference' => 'x'])->assertOk();

        expect($response->json('set'))->toBe([]);
    });

    it('can depend on state', function () {
        $form = Form::make([
            Checkbox::make('locked'),
            TextInput::make('name')->default('server')->readOnly(fn (Get $get): bool => (bool) $get('locked')),
        ]);

        expect($form->validate(['locked' => true, 'name' => 'tampered'])['name'])->toBe('server')
            ->and($form->validate(['locked' => false, 'name' => 'changed'])['name'])->toBe('changed');
    });
});

describe('state hooks', function () {
    it('formats state for the form and undoes it on save', function () {
        $order = existingOrder();
        $form = OperationsForm::make($order);

        expect($form->getData()['customer'])->toBe('ADA')
            ->and($form->toSchema()['state']['data']['customer'])->toBe('ADA');

        $form->save(['customer' => 'ADA', 'items' => []]);

        expect($order->fresh()->customer)->toBe('ada');
    });

    it('formats the fields inside rows', function () {
        $order = OperationsForm::make()->save(['customer' => 'Ada', 'items' => ['r1' => ['product' => 'desk', 'qty' => 1, 'price' => 2]]]);
        $data = OperationsForm::make($order->fresh())->getData();

        expect(array_values($data['items'])[0]['product'])->toBe('DESK');
    });

    it('writes to the field, not the form, for a field named data', function () {
        $form = Form::make([
            TextInput::make('other')->default('kept'),
            TextInput::make('data')->default('x')->formatStateUsing(fn (string $state): string => strtoupper($state)),
        ]);

        expect($form->getData())->toBe(['other' => 'kept', 'data' => 'X']);
    });

    it('treats data as an ordinary field name in closures and rules', function () {
        $form = Form::make([
            TextInput::make('other')->default('kept'),
            TextInput::make('data')->default('x'),
            TextInput::make('reader')->afterStateHydrated(function (Get $get, Set $set, Field $component): void {
                $component->state($get('data'));
                $set('data', 'written');
            }),
            TextInput::make('copy')->computed('data'),
            TextInput::make('shown')->visibleWhen(When::eq('data', 'written')),
        ]);

        expect($form->getData())->toMatchArray(['other' => 'kept', 'data' => 'written', 'reader' => 'x', 'copy' => 'written'])
            ->and(nodeOf($form, 'copy')['computed']['ast'])->toBe(['ref' => 'data.data'])
            ->and(nodeOf($form, 'shown')['rules']['visible']['path'])->toBe('data.data');
    });

    it('reads every other field after the form is filled', function () {
        $form = Form::make([
            TextInput::make('slug')->afterStateHydrated(fn (Get $get, Field $component) => $component->state($get('name').'-slug')),
            TextInput::make('name')->default('later'),
        ]);

        expect($form->getData()['slug'])->toBe('later-slug');
    });

    it('derives a locked value on save from a field the user can change', function () {
        $form = Form::make([
            TextInput::make('name'),
            TextInput::make('slug')->disabled()->saved()->dehydrateStateUsing(fn (Get $get): string => Str::slug($get('name'))),
        ]);

        expect($form->validate(['name' => 'Ada Lovelace', 'slug' => 'tampered'])['slug'])->toBe('ada-lovelace');
    });

    it('receives the cast value when dehydrating', function () {
        $field = TextInput::make('qty')->numeric()->dehydrateStateUsing(fn (int $state): int => $state * 2);

        expect(staticForm($field)->validate(['qty' => '4']))->toBe(['qty' => 8]);
    });
});

describe('saved', function () {
    it('leaves out a field with saved(false)', function (bool|Closure $saved) {
        expect(staticForm(TextInput::make('name')->saved($saved))->validate(['name' => 'Ada']))->toBe([]);
    })->with([
        'bool' => [false],
        'closure' => [fn () => fn (): bool => false],
    ]);

    it('keeps the server value of a disabled field with saved()', function () {
        $data = staticForm(TextInput::make('name')->default('server')->disabled()->saved())->validate(['name' => 'tampered']);

        expect($data)->toBe(['name' => 'server']);
    });
});

describe('display options', function () {
    it('leaves them off the node until they are used', function () {
        expect(nodeOf(staticForm(TextInput::make('name')), 'name'))
            ->not->toHaveKeys(['readOnly', 'autofocus', 'hiddenLabel', 'inlineLabel', 'extraAttributes']);
    });

    it('tells the browser when a closure turns one off', function () {
        $form = Form::make([
            TextInput::make('locked'),
            TextInput::make('name')
                ->readOnly(fn (Get $get): bool => $get('locked') === 'yes')
                ->hiddenLabel(fn (Get $get): bool => $get('locked') === 'yes')
                ->inlineLabel(fn (Get $get): bool => $get('locked') === 'yes'),
        ]);

        expect(nodeOf($form, 'name'))
            ->readOnly->toBeFalse()
            ->hiddenLabel->toBeFalse()
            ->inlineLabel->toBeFalse();
    });

    it('serializes them, tracking what closures read', function () {
        $form = Form::make([
            TextInput::make('name')
                ->readOnly()
                ->autofocus()
                ->hiddenLabel()
                ->inlineLabel(fn (): bool => true)
                ->extraAttributes(fn (Get $get): array => ['data-plan' => $get('plan')]),
            TextInput::make('plan')->default('pro'),
        ]);

        expect(nodeOf($form, 'name'))
            ->readOnly->toBeTrue()
            ->autofocus->toBeTrue()
            ->hiddenLabel->toBeTrue()
            ->inlineLabel->toBeTrue()
            ->extraAttributes->toBe(['data-plan' => 'pro'])
            ->deps->toContain('data.plan');
    });
});
