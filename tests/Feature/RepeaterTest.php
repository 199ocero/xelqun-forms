<?php

use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\Checkbox;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Repeater\TableColumn;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Group;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\Tests\Fixtures\NestedRelationshipForm;
use Xelqun\Forms\Tests\Fixtures\Order;
use Xelqun\Forms\Tests\Fixtures\OrderForm;
use Xelqun\Forms\Tests\Fixtures\Review;

/**
 * @return array<string, array<int, string>>
 */
function rowErrors(Form $form, array $data): array
{
    try {
        $form->validate($data);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

function metaForm(Closure $schema): OrderForm
{
    $form = new class extends OrderForm
    {
        public static Closure $schema;

        public function schema(): array
        {
            return [TextInput::make('customer'), ...(self::$schema)()];
        }
    };
    $form::$schema = $schema;

    return $form;
}

/**
 * @return array<string, mixed>
 */
function repeaterProps(Repeater $repeater): array
{
    return (array) Form::make([$repeater])->toSchema()['components'][0]['props'];
}

function item(array $reviews = [], string $product = 'Desk'): array
{
    return ['product' => $product, 'qty' => 1, 'price' => 2, 'reviews' => $reviews];
}

describe('nested relationships', function () {
    it('saves two levels and fills them back', function () {
        $order = NestedRelationshipForm::make()->save(['customer' => 'Ada', 'items' => [
            'a' => item(['x' => ['stars' => '5'], 'y' => ['stars' => '3']]),
            'b' => item(['z' => ['stars' => '1']], 'Lamp'),
        ]]);

        $items = $order->items()->orderBy('id')->get();

        expect($items[0]->reviews()->orderBy('id')->pluck('stars')->all())->toBe([5, 3])
            ->and($items[1]->reviews()->pluck('stars')->all())->toBe([1]);

        $rows = NestedRelationshipForm::make($order->fresh())->getData()['items'];
        $itemKey = Repeater::RECORD_KEY_PREFIX.$items[0]->id;
        $reviewKey = Repeater::RECORD_KEY_PREFIX.$items[0]->reviews()->orderBy('id')->first()->id;

        expect(array_keys($rows))->toContain($itemKey)
            ->and($rows[$itemKey]['reviews'][$reviewKey]['stars'])->toBe(5);
    });

    it('updates, adds and deletes inner rows without touching other rows', function () {
        $order = NestedRelationshipForm::make()->save(['customer' => 'Ada', 'items' => [
            'a' => item(['x' => ['stars' => '5'], 'y' => ['stars' => '3']]),
            'b' => item(['z' => ['stars' => '1']], 'Lamp'),
        ]]);
        [$first, $second] = $order->items()->orderBy('id')->get()->all();
        [$kept, $dropped] = $first->reviews()->orderBy('id')->get()->all();
        $other = $second->reviews()->sole();
        $key = Repeater::RECORD_KEY_PREFIX;

        NestedRelationshipForm::make($order->fresh())->save(['customer' => 'Ada', 'items' => [
            $key.$first->id => item([$key.$kept->id => ['stars' => '4'], 'new' => ['stars' => '2']]),
            $key.$second->id => item([$key.$other->id => ['stars' => '1']], 'Lamp'),
        ]]);

        expect($kept->fresh()->stars)->toBe(4)
            ->and(Review::query()->find($dropped->id))->toBeNull()
            ->and($first->reviews()->count())->toBe(2)
            ->and($other->fresh()->stars)->toBe(1);
    });

    it('never takes over a row of another record', function () {
        $victim = NestedRelationshipForm::make()->save(['customer' => 'Victim', 'items' => ['a' => item(['x' => ['stars' => '5']])]]);
        $review = $victim->items()->sole()->reviews()->sole();
        $order = NestedRelationshipForm::make()->save(['customer' => 'Ada', 'items' => ['a' => item()]]);
        $item = $order->items()->sole();
        $key = Repeater::RECORD_KEY_PREFIX;

        expect(rowErrors(NestedRelationshipForm::make($order->fresh()), ['customer' => 'Ada', 'items' => [
            $key.$item->id => item([$key.$review->id => ['stars' => '1']]),
        ]]))->toHaveKey("data.items.{$key}{$item->id}.reviews");

        expect($review->fresh()->order_item_id)->toBe($victim->items()->sole()->id)
            ->and($review->fresh()->stars)->toBe(5);
    });

    it('names both levels in an error', function () {
        $form = Form::make([
            Repeater::make('sections')->itemLabel('Section')->schema([
                Repeater::make('lines')->itemLabel('Line')->schema([TextInput::make('qty')->required()]),
            ]),
        ]);
        $errors = rowErrors($form, ['sections' => ['a' => ['lines' => ['x' => ['qty' => 1]]], 'b' => ['lines' => ['y' => ['qty' => 1], 'z' => ['qty' => null]]]]]);

        expect($errors['data.sections.b.lines.z.qty'][0])->toContain('Qty (Section 2, Line 2)');
    });
});

describe('rows stored in one column', function () {
    it('saves plain lists at every level and fills them back', function () {
        $form = metaForm(fn () => [
            Repeater::make('meta')->schema([
                TextInput::make('name'),
                Repeater::make('lines')->schema([TextInput::make('qty')->numeric()]),
            ]),
        ]);

        $order = $form::make()->save(['customer' => 'Ada', 'meta' => [
            'a' => ['name' => 'One', 'lines' => ['x' => ['qty' => 1], 'y' => ['qty' => 2]]],
        ]]);

        expect($order->fresh()->meta)->toBe([['name' => 'One', 'lines' => [['qty' => 1], ['qty' => 2]]]]);

        $rows = $form::make($order->fresh())->getData()['meta'];

        expect(array_values(array_values($rows)[0]['lines']))->toBe([['qty' => 1], ['qty' => 2]]);
    });

    it('returns nested rows as lists from validate(), inside a group too', function () {
        $form = Form::make([
            Repeater::make('sections')->schema([
                Group::make([
                    Repeater::make('lines')->schema([TextInput::make('qty')]),
                ]),
            ]),
        ]);

        expect($form->validate(['sections' => ['a' => ['lines' => ['x' => ['qty' => '1']]]]]))
            ->toBe(['sections' => [['lines' => [['qty' => '1']]]]]);
    });
});

describe('simple', function () {
    it('holds a flat list on the way in and out', function () {
        $form = metaForm(fn () => [Repeater::make('meta')->simple(TextInput::make('email')->email())]);

        $order = $form::make()->save(['customer' => 'Ada', 'meta' => [
            'a' => ['email' => 'a@example.com'],
            'b' => ['email' => 'b@example.com'],
        ]]);

        expect($order->fresh()->meta)->toBe(['a@example.com', 'b@example.com']);

        $rows = $form::make($order->fresh())->getData()['meta'];

        expect(array_column(array_values($rows), 'email'))->toBe(['a@example.com', 'b@example.com'])
            ->and($form::make()->validate(['meta' => ['a' => ['email' => 'x@example.com']]])['meta'])->toBe(['x@example.com']);
    });

    it('keeps the rules of its field and hides the label', function () {
        $make = fn (): Repeater => Repeater::make('v')->simple(TextInput::make('email')->email());

        expect(rowErrors(Form::make([$make()]), ['v' => ['a' => ['email' => 'nope']]]))->toHaveKey('data.v.a.email')
            ->and(repeaterProps($make())['simple'])->toBeTrue()
            ->and(repeaterProps($make())['template'][0]['hiddenLabel'])->toBeTrue();
    });

    it('works inside another repeater', function () {
        $form = Form::make([
            Repeater::make('rows')->schema([Repeater::make('emails')->simple(TextInput::make('email'))]),
        ]);

        expect($form->validate(['rows' => ['a' => ['emails' => ['x' => ['email' => 'e']]]]]))
            ->toBe(['rows' => [['emails' => ['e']]]]);
    });
});

describe('addable and deletable', function () {
    function storedRows(Closure $configure): array
    {
        $form = metaForm(fn () => [$configure(Repeater::make('meta')->schema([TextInput::make('name')]))]);
        $order = Order::query()->create(['customer' => 'Ada', 'meta' => [['name' => 'One'], ['name' => 'Two']]]);

        return [$form::make($order), $form::make($order)->getData()['meta']];
    }

    it('drops a row the browser added', function () {
        [$form, $rows] = storedRows(fn (Repeater $repeater) => $repeater->addable(false));

        expect($form->validate(['customer' => 'Ada', 'meta' => [...$rows, 'forged' => ['name' => 'Three']]])['meta'])
            ->toBe([['name' => 'One'], ['name' => 'Two']]);
    });

    it('brings back a row the browser removed', function () {
        [$form, $rows] = storedRows(fn (Repeater $repeater) => $repeater->deletable(false));

        expect($form->validate(['customer' => 'Ada', 'meta' => array_slice($rows, 0, 1)])['meta'])
            ->toBe([['name' => 'One'], ['name' => 'Two']]);
    });

    it('brings back every row when the field is left out', function () {
        [$form] = storedRows(fn (Repeater $repeater) => $repeater->deletable(false));

        expect($form->validate(['customer' => 'Ada'])['meta'])->toHaveCount(2);
    });

    it('still lets rows be edited', function () {
        [$form, $rows] = storedRows(fn (Repeater $repeater) => $repeater->addable(false)->deletable(false));
        $keys = array_keys($rows);

        expect($form->validate(['customer' => 'Ada', 'meta' => [$keys[0] => ['name' => 'Changed'], $keys[1] => ['name' => 'Two']]])['meta'])
            ->toBe([['name' => 'Changed'], ['name' => 'Two']]);
    });

    it('decides on locked values, not posted ones', function () {
        $make = fn (): Form => Form::make([
            TextInput::make('plan')->default('free')->disabled(),
            Repeater::make('rows')->addable(fn (Get $get): bool => $get('plan') === 'pro')->schema([TextInput::make('name')]),
        ]);

        expect($make()->validate(['plan' => 'pro', 'rows' => ['forged' => ['name' => 'x']]])['rows'])->toBe([]);
    });

    it('tells the browser, also when a closure turns one off', function () {
        expect(repeaterProps(Repeater::make('v')->schema([])))->toMatchArray(['addable' => true, 'deletable' => true])
            ->and(repeaterProps(Repeater::make('v')->addable(fn (): bool => false)->deletable(false)->schema([])))->toMatchArray(['addable' => false, 'deletable' => false]);
    });
});

describe('distinct', function () {
    it('refuses a value another row has', function () {
        $form = fn (): Form => Form::make([Repeater::make('rows')->schema([TextInput::make('code')->distinct()])]);

        expect(rowErrors($form(), ['rows' => ['a' => ['code' => 'x'], 'b' => ['code' => 'y']]]))->toBe([])
            ->and(rowErrors($form(), ['rows' => ['a' => ['code' => 'x'], 'b' => ['code' => 'x'], 'c' => ['code' => 'y']]]))
            ->toHaveKeys(['data.rows.a.code', 'data.rows.b.code'])
            ->not->toHaveKey('data.rows.c.code');
    });

    it('ignores blanks', function () {
        $form = Form::make([Repeater::make('rows')->schema([TextInput::make('code')->distinct()])]);

        expect(rowErrors($form, ['rows' => ['a' => ['code' => null], 'b' => ['code' => null]]]))->toBe([]);
    });

    it('lets only one row be on', function () {
        $form = fn (): Form => Form::make([Repeater::make('rows')->schema([Checkbox::make('main')->distinct()])]);

        expect(rowErrors($form(), ['rows' => ['a' => ['main' => true], 'b' => ['main' => false], 'c' => ['main' => false]]]))->toBe([])
            ->and(rowErrors($form(), ['rows' => ['a' => ['main' => true], 'b' => ['main' => true]]]))->toHaveKey('data.rows.b.main');
    });

    it('compares each value of a multiple field', function () {
        $form = fn (): Form => Form::make([Repeater::make('rows')->schema([
            Select::make('tags')->multiple()->options(['a' => 'A', 'b' => 'B', 'c' => 'C'])->distinct(),
        ])]);

        expect(rowErrors($form(), ['rows' => ['x' => ['tags' => ['a', 'b']], 'y' => ['tags' => ['c']]]]))->toBe([])
            ->and(rowErrors($form(), ['rows' => ['x' => ['tags' => ['a', 'b']], 'y' => ['tags' => ['b', 'c']]]]))->toHaveKey('data.rows.y.tags');
    });

    it('finds its rows through a layout, and stays within its own repeater', function () {
        $form = fn (): Form => Form::make([
            Repeater::make('sections')->schema([
                Repeater::make('lines')->schema([Group::make([TextInput::make('code')->distinct()])]),
            ]),
        ]);

        expect(rowErrors($form(), ['sections' => [
            'a' => ['lines' => ['x' => ['code' => 'same']]],
            'b' => ['lines' => ['y' => ['code' => 'same']]],
        ]]))->toBe([])
            ->and(rowErrors($form(), ['sections' => ['a' => ['lines' => ['x' => ['code' => 'same'], 'y' => ['code' => 'same']]]]]))
            ->toHaveKey('data.sections.a.lines.y.code');
    });

    it('does nothing outside a repeater, and takes a custom message', function () {
        expect(rowErrors(Form::make([TextInput::make('code')->distinct()]), ['code' => 'x']))->toBe([]);

        $form = Form::make([Repeater::make('rows')->schema([TextInput::make('code')->distinct()->validationMessages(['distinct' => 'Taken above.'])])]);

        expect(rowErrors($form, ['rows' => ['a' => ['code' => 'x'], 'b' => ['code' => 'x']]])['data.rows.a.code'])->toBe(['Taken above.']);
    });
});

it('sends its display options only when used', function () {
    expect(repeaterProps(Repeater::make('v')->schema([])))
        ->not->toHaveKeys(['cloneable', 'collapsible', 'collapsed', 'deleteConfirmation', 'grid', 'simple', 'table'])
        ->toMatchArray(['itemNumbers' => true, 'draggable' => true]);

    expect(repeaterProps(
        Repeater::make('v')
            ->schema([])
            ->cloneable()
            ->collapsed()
            ->itemNumbers(false)
            ->reorderableWithDragAndDrop(false)
            ->grid(2)
            ->requiresDeleteConfirmation(message: 'Sure?')
            ->table([TableColumn::make('Name')->width('200px')->markAsRequired(), TableColumn::make('Role')->hiddenHeaderLabel()]),
    ))->toMatchArray([
        'cloneable' => true,
        'collapsible' => true,
        'collapsed' => true,
        'itemNumbers' => false,
        'draggable' => false,
        'grid' => ['default' => 1, 'md' => 2],
        'deleteConfirmation' => 'Sure?',
        'table' => [
            ['label' => 'Name', 'width' => '200px', 'hiddenLabel' => false, 'required' => true],
            ['label' => 'Role', 'width' => null, 'hiddenLabel' => true, 'required' => false],
        ],
    ]);
});

it('has a default delete message, and can turn it off', function () {
    expect(repeaterProps(Repeater::make('v')->schema([])->requiresDeleteConfirmation())['deleteConfirmation'])->toContain('Delete this item')
        ->and(repeaterProps(Repeater::make('v')->schema([])->requiresDeleteConfirmation(fn (): bool => false)))->toHaveKey('deleteConfirmation', null);
});

it('can not be dragged when it can not be ordered', function () {
    expect(repeaterProps(Repeater::make('v')->reorderable(false)->schema([]))['draggable'])->toBeFalse();
});

it('tells the browser which fields hold files', function () {
    $form = new class extends OrderForm
    {
        public function schema(): array
        {
            return [TextInput::make('customer'), FileUpload::make('attachment')];
        }
    };
    $nodes = $form::make()->toSchema()['components'];

    expect($nodes[0])->not->toHaveKey('acceptsUploads')
        ->and($nodes[1]['acceptsUploads'])->toBeTrue();
});
