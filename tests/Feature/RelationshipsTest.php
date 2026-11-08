<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\MorphToSelect;
use Xelqun\Forms\Components\MorphToSelect\Type;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Components\Toggle;
use Xelqun\Forms\Contracts\PersistsBeforeSave;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Group;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\Tests\Fixtures\Country;
use Xelqun\Forms\Tests\Fixtures\Order;
use Xelqun\Forms\Tests\Fixtures\OrderForm;
use Xelqun\Forms\Tests\Fixtures\OrderItem;
use Xelqun\Forms\Tests\Fixtures\OrderNote;
use Xelqun\Forms\Tests\Fixtures\PlainRowsField;
use Xelqun\Forms\Tests\Fixtures\Region;
use Xelqun\Forms\Tests\Fixtures\RelationsForm;
use Xelqun\Forms\Tests\Fixtures\Tag;

beforeEach(function () {
    RelationsForm::$calls = [];
    RelationsForm::$hideRelations = false;
    RelationsForm::$failIn = null;
});

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function relationsInput(array $data = []): array
{
    return ['customer' => 'Ada', 'note' => ['body' => 'Hi'], 'items' => [], ...$data];
}

function repeaterForm(Closure $configure): OrderForm
{
    $form = new class extends OrderForm
    {
        public static Closure $configure;

        public function schema(): array
        {
            return [
                TextInput::make('customer'),
                (self::$configure)(Repeater::make('items')->relationship()->schema([
                    TextInput::make('product'),
                    TextInput::make('qty')->numeric()->default(1),
                    TextInput::make('price')->numeric()->default(1),
                    TextInput::make('subtotal')->numeric()->computed('qty * price'),
                ])),
            ];
        }
    };
    $form::$configure = $configure;

    return $form;
}

describe('save hooks', function () {
    it('runs them in order around the writes', function () {
        RelationsForm::make()->save(relationsInput());

        expect(RelationsForm::$calls)->toBe(['beforeSave 0', 'mutateBeforeSave', 'afterSave 1', 'afterCommit Ada']);
    });

    it('rolls everything back when a hook inside the transaction fails', function (string $hook) {
        RelationsForm::$failIn = $hook;

        expect(fn () => RelationsForm::make()->save(relationsInput()))->toThrow(RuntimeException::class);

        expect(Order::query()->count())->toBe(0)
            ->and(OrderNote::query()->count())->toBe(0)
            ->and(RelationsForm::$calls)->not->toContain('afterCommit Ada');
    })->with(['beforeSave', 'afterSave']);

    it('reports a failed afterCommit and still returns the saved record', function () {
        Exceptions::fake();
        RelationsForm::$failIn = 'afterCommit';
        $form = RelationsForm::make();

        $order = $form->save(relationsInput());

        expect($order->exists)->toBeTrue()
            ->and($form->getRecord()->is($order))->toBeTrue();

        Exceptions::assertReported(RuntimeException::class);
    });

    it('waits for the outermost transaction', function () {
        DB::transaction(function (): void {
            RelationsForm::make()->save(relationsInput());

            expect(RelationsForm::$calls)->not->toContain('afterCommit Ada');
        });

        expect(RelationsForm::$calls)->toContain('afterCommit Ada');
    });

    it('never reports a commit for an outer rollback', function () {
        try {
            DB::transaction(function (): void {
                RelationsForm::make()->save(relationsInput());

                throw new RuntimeException('Outer failure');
            });
        } catch (RuntimeException) {
        }

        expect(RelationsForm::$calls)->not->toContain('afterCommit Ada');
    });

    it('offers a stage before the save to a component that wants one', function () {
        $country = Country::query()->create(['name' => 'Spain']);
        $field = new class('owner') extends Field implements PersistsBeforeSave
        {
            public int $countryId;

            public function getType(): string
            {
                return 'acme/owner';
            }

            public function shouldPersistItself(): bool
            {
                return true;
            }

            public function persistBeforeSave(Model $record, mixed $state): void
            {
                expect($record->exists)->toBeFalse();

                $record->setAttribute('country_id', $this->countryId);
            }

            public function persist(Model $record, mixed $state): void
            {
                expect($record->exists)->toBeTrue();
            }
        };
        $field->countryId = $country->id;

        $form = new class extends OrderForm
        {
            public static Field $field;

            public function schema(): array
            {
                return [TextInput::make('customer'), self::$field];
            }
        };
        $form::$field = $field;

        $order = $form::make()->save(['customer' => 'Ada', 'owner' => 'x']);

        expect($order->fresh()->country_id)->toBe($country->id);
    });
});

describe('BelongsToMany select', function () {
    beforeEach(function () {
        $this->php = Tag::query()->create(['name' => 'php']);
        $this->vue = Tag::query()->create(['name' => 'vue']);
        $this->internal = Tag::query()->create(['name' => 'internal', 'group' => 'staff']);
    });

    it('attaches with pivot data and fills back', function () {
        $order = RelationsForm::make()->save(relationsInput(['tags' => [$this->php->id, (string) $this->vue->id]]));

        expect($order->tags()->orderBy('name')->pluck('added_by', 'name')->all())
            ->toBe(['php' => "order-{$order->id}", 'vue' => "order-{$order->id}"])
            ->and(RelationsForm::make($order->fresh())->getData()['tags'])->toBe([$this->php->id, $this->vue->id]);
    });

    it('only attaches and detaches the difference', function () {
        $order = Order::query()->create(['customer' => 'Ada']);
        $order->tags()->attach($this->php->id, ['added_by' => 'kept']);
        $order->tags()->attach($this->vue->id);

        RelationsForm::make($order)->save(relationsInput(['tags' => [$this->php->id]]));

        expect($order->tags()->pluck('added_by', 'name')->all())->toBe(['php' => 'kept']);
    });

    it('never detaches a record outside its query', function () {
        $order = Order::query()->create(['customer' => 'Ada']);
        $order->tags()->attach([$this->php->id, $this->internal->id]);

        $form = RelationsForm::make($order);

        expect($form->getData()['tags'])->toBe([$this->php->id]);

        $form->save(relationsInput(['tags' => []]));

        expect($order->tags()->pluck('name')->all())->toBe(['internal']);
    });

    it('attaches a record once, however often it is posted', function () {
        $order = RelationsForm::make()->save(relationsInput(['tags' => [$this->php->id, (string) $this->php->id, $this->php->id]]));

        expect(DB::table('order_tag')->where('order_id', $order->id)->count())->toBe(1);
    });

    it('refuses a posted record outside its query', function () {
        expect(fn () => RelationsForm::make()->save(relationsInput(['tags' => [$this->internal->id]])))
            ->toThrow(ValidationException::class);

        expect(DB::table('order_tag')->count())->toBe(0);
    });

    it('changes nothing when hidden or not submitted', function (array $input, bool $hidden) {
        $order = Order::query()->create(['customer' => 'Ada']);
        $order->tags()->attach($this->php->id);
        RelationsForm::$hideRelations = $hidden;

        RelationsForm::make($order)->save($input);

        expect($order->tags()->count())->toBe(1);
    })->with([
        'hidden, with a tampered list' => [['customer' => 'Ada', 'tags' => [], 'items' => []], true],
    ]);

    it('labels options from their record everywhere', function () {
        $form = RelationsForm::make();
        $select = $form->findComponent('data.tags');

        expect($select->getOptions())->toBe([$this->php->id => '#php', $this->vue->id => '#vue'])
            ->and($select->getSearchResults('vu'))->toBe([['value' => $this->vue->id, 'label' => '#vue']]);

        $form->setStateValue('data.tags', [$this->php->id]);

        expect($select->getSelectedOptions())->toBe([$this->php->id => '#php']);
    });

    it('needs multiple() and a title attribute', function () {
        $form = new class extends OrderForm
        {
            public function schema(): array
            {
                return [Select::make('tags')->relationship(titleAttribute: 'name')];
            }
        };

        expect(fn () => $form::make(Order::query()->create(['customer' => 'Ada'])))->toThrow(LogicException::class, 'needs multiple()')
            ->and(fn () => Select::make('tags')->relationship('tags'))->toThrow(FormDefinitionException::class);
    });
});

describe('MorphToSelect', function () {
    beforeEach(function () {
        $this->country = Country::query()->create(['name' => 'Spain']);
        $this->area = Region::query()->create(['country_id' => $this->country->id, 'name' => 'Madrid']);
        $this->secret = Region::query()->create(['country_id' => $this->country->id, 'name' => 'Secret']);
    });

    it('saves both columns and fills them back', function () {
        $order = RelationsForm::make()->save(relationsInput(['owner_type' => Region::class, 'owner_id' => $this->area->id]));

        expect($order->fresh()->owner)->toBeInstanceOf(Region::class)
            ->and($order->fresh()->owner->is($this->area))->toBeTrue();

        $form = RelationsForm::make($order->fresh());

        expect($form->getData())->toMatchArray(['owner_type' => Region::class, 'owner_id' => $this->area->id])
            ->and($form->findComponent('data.owner_id')->getLabel())->toBe('Area')
            ->and($form->findComponent('data.owner_id')->getOptions())->toBe([$this->area->id => 'MADRID']);
    });

    it('still opens a record whose stored type no longer exists', function () {
        $order = Order::query()->create(['customer' => 'Ada', 'owner_type' => 'App\\Models\\Removed', 'owner_id' => 1]);

        $form = RelationsForm::make($order);

        expect($form->toSchema()['state']['data']['owner_type'])->toBe('App\\Models\\Removed')
            ->and($form->findComponent('data.owner_id')->getOptions())->toBe([]);
    });

    it('refuses a type that was not declared', function () {
        expect(fn () => RelationsForm::make()->save(relationsInput(['owner_type' => Order::class, 'owner_id' => 1])))
            ->toThrow(ValidationException::class);
    });

    it('treats a type that is not text as invalid', function () {
        try {
            RelationsForm::make()->validate(relationsInput(['owner_type' => ['x']]));
            $this->fail('Validation should have failed.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('data.owner_type');
        }

        $form = RelationsForm::make();

        $this->updateForm($form, ['data.owner_type'], [...$form->getData(), 'owner_type' => ['x']], ['data.owner_id' => ['data.owner_type']])->assertOk();
    });

    it('refuses a record outside the type\'s query or of another type', function (Closure $input) {
        expect(fn () => RelationsForm::make()->save(relationsInput($input($this))))->toThrow(ValidationException::class);
    })->with([
        'scoped out' => [fn ($test): array => ['owner_type' => Region::class, 'owner_id' => $test->secret->id]],
        'no type' => [fn ($test): array => ['owner_id' => $test->area->id]],
    ]);

    it('clears the record when the type changes', function () {
        $form = RelationsForm::make(Order::query()->create(['customer' => 'Ada', 'owner_type' => Country::class, 'owner_id' => $this->country->id]));

        $response = $this->updateForm($form, ['data.owner_type'], [...$form->getData(), 'owner_type' => Region::class])->assertOk();

        expect($response->json('set'))->toHaveKey('data.owner_id', null);
    });

    it('loads the options of the chosen type when it changes', function () {
        $form = RelationsForm::make();
        $node = $this->formNode($form, 'data.owner_id');

        $response = $this->updateForm($form, ['data.owner_type'], [...$form->getData(), 'owner_type' => Region::class], ['data.owner_id' => $node['deps']]);

        expect($response->json('schema')['data.owner_id']['props']['options'])->toBe([['value' => $this->area->id, 'label' => 'MADRID']]);
    });

    it('takes field names and the schema\'s own dependencies in the testing helpers', function () {
        $form = RelationsForm::make();

        expect($this->formNode($form, 'owner_id')['key'])->toBe('data.owner_id');

        $response = $this->updateForm($form, ['owner_type'], [...$form->getData(), 'owner_type' => Region::class]);

        expect($response->json('schema')['data.owner_id']['props']['options'])->toBe([['value' => $this->area->id, 'label' => 'MADRID']]);
    });

    it('searches on the server and checks the result', function () {
        $form = new class extends OrderForm
        {
            public function schema(): array
            {
                return [
                    MorphToSelect::make('owner')->searchable()->types([
                        Type::make(Region::class)->titleAttribute('name')->modifyOptionsQueryUsing(fn (Builder $query) => $query->where('name', '!=', 'Secret')),
                    ]),
                ];
            }
        };

        $instance = $form::make();
        $instance->setStateValue('data.owner_type', Region::class);
        $select = $instance->findComponent('data.owner_id');

        expect($select->getSearchResults('mad'))->toBe([['value' => $this->area->id, 'label' => 'Madrid']])
            ->and($select->getSearchResults('sec'))->toBe([])
            ->and(fn () => $form::make()->validate(['owner_type' => Region::class, 'owner_id' => $this->secret->id]))->toThrow(ValidationException::class)
            ->and($form::make()->validate(['owner_type' => Region::class, 'owner_id' => $this->area->id])['owner_id'])->toBe($this->area->id);
    });

    it('needs a MorphTo relationship and a title attribute', function () {
        $wrong = new class extends OrderForm
        {
            public function schema(): array
            {
                return [MorphToSelect::make('country')];
            }
        };
        $untitled = new class extends OrderForm
        {
            public function schema(): array
            {
                return [MorphToSelect::make('owner')->types([Type::make(Region::class)])];
            }
        };

        expect(fn () => $wrong::make())->toThrow(LogicException::class, 'must be a MorphTo')
            ->and(fn () => $untitled::make()->validate(['owner_type' => Region::class]))->toThrow(FormDefinitionException::class);
    });
});

describe('relationship group', function () {
    it('creates a HasOne record and updates the same one', function () {
        $order = RelationsForm::make()->save(relationsInput());
        $note = OrderNote::query()->sole();

        expect($note->order_id)->toBe($order->id)->and($note->body)->toBe('Hi');

        $form = RelationsForm::make($order->fresh());

        expect($form->getData()['note']['body'])->toBe('Hi');

        $form->save(relationsInput(['note' => ['body' => 'Changed']]));

        expect(OrderNote::query()->sole())->id->toBe($note->id)->body->toBe('Changed');
    });

    it('saves a BelongsTo record first and sets the key', function () {
        $order = RelationsForm::make()->save(relationsInput(['country' => ['name' => 'Spain']]));

        expect($order->fresh()->country->name)->toBe('Spain');

        RelationsForm::make($order->fresh())->save(relationsInput(['country' => ['name' => 'Portugal']]));

        expect(Country::query()->sole()->name)->toBe('Portugal');
    });

    it('creates nothing for a blank group, but keeps updating an existing record', function () {
        $order = RelationsForm::make()->save(relationsInput(['country' => ['name' => '']]));

        expect(Country::query()->count())->toBe(0)
            ->and($order->fresh()->country_id)->toBeNull();

        $withToggle = new class extends OrderForm
        {
            public function schema(): array
            {
                return [
                    TextInput::make('customer'),
                    Group::make([TextInput::make('body'), Toggle::make('stamp')])->relationship('note'),
                ];
            }
        };

        expect($withToggle::make()->save(['customer' => 'Ada', 'note' => ['body' => null, 'stamp' => false]])->note()->exists())->toBeFalse();

        $country = Country::query()->create(['name' => 'Spain']);
        $order->update(['country_id' => $country->id]);

        RelationsForm::make($order->fresh())->save(relationsInput(['country' => ['name' => 'Portugal']]));

        expect($country->fresh()->name)->toBe('Portugal');
    });

    it('leaves the record alone while hidden', function () {
        $order = RelationsForm::make()->save(relationsInput());
        RelationsForm::$hideRelations = true;

        RelationsForm::make($order->fresh())->save(['customer' => 'Ada', 'note' => ['body' => 'Tampered'], 'items' => []]);

        expect(OrderNote::query()->sole()->body)->toBe('Hi');
    });

    it('reports errors on the nested path', function () {
        try {
            RelationsForm::make()->save(relationsInput(['note' => ['body' => null]]));
            $this->fail('Validation should have failed.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('data.note.body');
        }
    });

    it('resolves siblings inside it and the form with $.', function () {
        $data = RelationsForm::make()->validate(relationsInput());

        expect($data['note'])->toBe(['body' => 'Hi', 'stamp' => null]);

        $form = Form::make([
            TextInput::make('name')->default('Form'),
            Group::make()->schema([
                TextInput::make('title')->default('Sibling'),
                TextInput::make('copy')->default(fn (Get $get): string => $get('title').' and '.$get('$.name')),
            ]),
        ]);

        expect($form->getData()['copy'])->toBe('Sibling and Form');
    });

    it('does not write a read-only value over the stored one', function () {
        $order = Order::query()->create(['customer' => 'Ada']);
        $order->note()->create(['body' => 'Hi', 'stamp' => 'stored']);

        RelationsForm::make($order)->save(relationsInput(['note' => ['body' => 'New', 'stamp' => 'tampered']]));

        expect(OrderNote::query()->sole())->body->toBe('New')->stamp->toBe('stored');
    });

    it('works inside a repeater row', function () {
        $order = RelationsForm::make()->save(relationsInput(['items' => ['r1' => ['product' => 'Desk', 'qty' => 1, 'price' => 1, 'review' => ['stars' => 4]]]]));
        $item = $order->items()->sole();

        expect($item->review->stars)->toBe(4);

        $form = RelationsForm::make($order->fresh());
        $rowKey = Repeater::RECORD_KEY_PREFIX.$item->id;

        expect($form->getData()['items'][$rowKey]['review']['stars'])->toBe(4);

        $form->save(relationsInput(['items' => [$rowKey => ['product' => 'Desk', 'qty' => 1, 'price' => 1, 'review' => ['stars' => 2]]]]));

        expect($item->review()->sole()->stars)->toBe(2);
    });

    it('puts the defaults of a row added in the browser at their real paths', function () {
        $form = new class extends OrderForm
        {
            public function schema(): array
            {
                return [Repeater::make('items')->relationship()->schema([
                    TextInput::make('product')->default('Desk'),
                    Group::make([TextInput::make('stars')->default(5)])->relationship('review'),
                ])];
            }
        };

        expect($form::make()->findComponent('data.items')->getNewRowState())
            ->toBe(['product' => 'Desk', 'review' => ['stars' => 5]]);
    });

    it('gives its fields the related model for their relationships and uploads', function () {
        Storage::fake('public');
        Storage::fake('local');

        $form = new class extends OrderForm
        {
            public function schema(): array
            {
                return [
                    TextInput::make('customer'),
                    Group::make([
                        TextInput::make('product'),
                        TextInput::make('qty')->numeric()->default(1),
                        TextInput::make('price')->numeric()->default(1),
                        TextInput::make('subtotal')->numeric()->default(1),
                        FileUpload::make('attachment')->disk('public'),
                        Repeater::make('reviews')->relationship()->schema([TextInput::make('stars')->numeric()]),
                    ])->relationship('firstItem'),
                ];
            }
        };
        $instance = $form::make();
        $token = $this->uploadToForm($instance, 'data.firstItem.attachment', UploadedFile::fake()->create('a.pdf', 1, 'application/pdf'))->assertOk()->json('token');

        $order = $form::make()->save(['customer' => 'Ada', 'firstItem' => [
            'product' => 'Desk', 'qty' => 1, 'price' => 1, 'subtotal' => 1, 'attachment' => $token, 'reviews' => ['r1' => ['stars' => 5]],
        ]]);

        $item = OrderItem::query()->sole();

        expect($item->order_id)->toBe($order->id)
            ->and($item->reviews()->sole()->stars)->toBe(5);

        Storage::disk('public')->assertExists($item->attachment);
    });

    it('sends an object when every field in it is hidden', function () {
        $form = new class extends OrderForm
        {
            public function schema(): array
            {
                return [Group::make([TextInput::make('body')->hidden(fn (): bool => true)])->relationship('note')];
            }
        };

        expect(json_encode($form::make()->toSchema()['state']['data']))->toBe('{"note":{}}');
    });

    it('stays a plain layout without a relationship', function () {
        $form = Form::make([Group::make([TextInput::make('name')->default('Ada')])]);

        expect($form->validate(['name' => 'Grace']))->toBe(['name' => 'Grace']);
    });

    it('refuses a relationship it can not edit', function () {
        $form = new class extends OrderForm
        {
            public function schema(): array
            {
                return [Group::make([TextInput::make('product')])->relationship('items')];
            }
        };

        expect(fn () => $form::make(Order::query()->create(['customer' => 'Ada'])))->toThrow(LogicException::class, 'must be a HasOne');
    });
});

it('asks any field with rows for its row model, not only a Repeater', function () {
    $form = OrderForm::make();
    $rows = PlainRowsField::make('rows')->mount($form, null, 'data', '0');
    $child = TextInput::make('name')->mount($form, $rows, 'data.rows.a', '0');

    expect($form->getModelInstanceFor($child))->toBeInstanceOf(Tag::class);
});

it('leaves out a repeater inside a related row when it has saved(false)', function () {
    $form = new class extends OrderForm
    {
        public function schema(): array
        {
            return [
                TextInput::make('customer'),
                Repeater::make('items')->relationship()->schema([
                    TextInput::make('product'),
                    TextInput::make('qty')->numeric()->default(1),
                    TextInput::make('price')->numeric()->default(1),
                    TextInput::make('subtotal')->numeric()->default(1),
                    Repeater::make('notes')->saved(false)->schema([TextInput::make('text')]),
                ]),
            ];
        }
    };

    $order = $form::make()->save(['customer' => 'Ada', 'items' => ['a' => [
        'product' => 'Desk', 'qty' => 1, 'price' => 1, 'subtotal' => 1, 'notes' => ['n' => ['text' => 'x']],
    ]]]);

    expect($order->items()->sole()->product)->toBe('Desk');
});

describe('repeater relationship hooks', function () {
    function savedOrder(): Order
    {
        $order = Order::query()->create(['customer' => 'Ada']);
        $order->items()->create(['product' => 'Desk', 'qty' => 1, 'price' => 2, 'subtotal' => 2]);

        return $order;
    }

    function rowKey(Order $order): string
    {
        return Repeater::RECORD_KEY_PREFIX.$order->items()->sole()->id;
    }

    function row(string $product): array
    {
        return ['product' => $product, 'qty' => 1, 'price' => 2];
    }

    it('mutates data before filling, without saving it', function () {
        $order = savedOrder();
        $form = repeaterForm(fn (Repeater $repeater) => $repeater->mutateRelationshipDataBeforeFillUsing(
            fn (array $data): array => [...$data, 'product' => strtoupper($data['product'])],
        ))::make($order);

        expect($form->getData()['items'][rowKey($order)]['product'])->toBe('DESK')
            ->and($order->items()->sole()->product)->toBe('Desk');
    });

    it('mutates a new row and can skip it', function () {
        $class = repeaterForm(fn (Repeater $repeater) => $repeater->mutateRelationshipDataBeforeCreateUsing(
            fn (array $data): ?array => $data['product'] === 'Skip' ? null : [...$data, 'product' => "new {$data['product']}"],
        ));

        $order = $class::make()->save(['customer' => 'Ada', 'items' => ['a' => row('Desk'), 'b' => row('Skip')]]);

        expect($order->items()->pluck('product')->all())->toBe(['new Desk']);
    });

    it('mutates an existing row, and null keeps it as it is', function () {
        $order = savedOrder();
        $key = rowKey($order);
        $class = repeaterForm(fn (Repeater $repeater) => $repeater->mutateRelationshipDataBeforeSaveUsing(
            fn (array $data): ?array => $data['product'] === 'Skip' ? null : [...$data, 'product' => "saved {$data['product']}"],
        ));

        $class::make($order)->save(['customer' => 'Ada', 'items' => [$key => row('Skip')]]);

        expect($order->items()->sole()->product)->toBe('Desk');

        $class::make($order->fresh())->save(['customer' => 'Ada', 'items' => [$key => row('Lamp')]]);

        expect($order->items()->sole()->product)->toBe('saved Lamp');
    });

    it('runs the after hooks with the row and its record', function () {
        $order = savedOrder();
        $key = rowKey($order);
        $calls = new ArrayObject;
        $hook = fn (string $name) => function (Model $record, ?array $data = null) use ($name, $calls): void {
            $calls[] = [$name, $record->exists, $record->product, $data['product'] ?? null];
        };
        $class = repeaterForm(fn (Repeater $repeater) => $repeater
            ->afterCreate($hook('created'))
            ->afterUpdate($hook('updated'))
            ->afterDelete($hook('deleted')));

        $class::make($order)->save(['customer' => 'Ada', 'items' => [$key => row('Lamp'), 'new' => row('Chair')]]);

        expect($calls->getArrayCopy())->toBe([['updated', true, 'Lamp', 'Lamp'], ['created', true, 'Chair', 'Chair']]);

        $calls->exchangeArray([]);
        $class::make($order->fresh())->save(['customer' => 'Ada', 'items' => []]);

        expect($calls->getArrayCopy())->toHaveCount(2)
            ->and($calls[0][0])->toBe('deleted')
            ->and($calls[0][1])->toBeFalse();
    });

    it('does not run the after hook for a skipped row', function () {
        $calls = 0;
        $class = repeaterForm(fn (Repeater $repeater) => $repeater
            ->mutateRelationshipDataBeforeCreateUsing(fn (): ?array => null)
            ->afterCreate(function () use (&$calls): void {
                $calls++;
            }));

        $class::make()->save(['customer' => 'Ada', 'items' => ['a' => row('Desk')]]);

        expect($calls)->toBe(0);
    });
});

describe('a long list of related records', function () {
    $selectProps = function (Closure $select): array {
        $form = new class extends OrderForm
        {
            public static Closure $select;

            public function schema(): array
            {
                return [(self::$select)()];
            }
        };
        $form::$select = $select;

        return (array) json_decode(json_encode($form::make()->toSchema()['components'][0]['props']), true);
    };

    it('searches on the server past the limit, instead of sending every record', function () use ($selectProps) {
        Country::query()->insert(array_map(fn (int $index): array => ['name' => "Country {$index}"], range(1, Select::PRELOAD_LIMIT + 1)));

        $props = $selectProps(fn () => Select::make('country_id')->relationship('country', 'name'));

        $form = new class extends OrderForm
        {
            public function schema(): array
            {
                return [Select::make('country_id')->relationship('country', 'name')];
            }
        };

        $this->searchForm($form::make(), 'country_id', 'Country 50')->assertOk()->assertJsonPath('options.0.label', 'Country 50');

        expect($props)->toMatchArray(['async' => true, 'searchable' => true, 'options' => []])
            ->and($selectProps(fn () => Select::make('country_id')->relationship('country', 'name')->preload())['options'])->toHaveCount(Select::PRELOAD_LIMIT + 1);
    });

    it('sends them all up to the limit', function () use ($selectProps) {
        Country::query()->insert(array_map(fn (int $index): array => ['name' => "Country {$index}"], range(1, Select::PRELOAD_LIMIT)));

        expect($selectProps(fn () => Select::make('country_id')->relationship('country', 'name')))
            ->toMatchArray(['async' => false, 'searchable' => false])
            ->and($selectProps(fn () => Select::make('country_id')->relationship('country', 'name'))['options'])->toHaveCount(Select::PRELOAD_LIMIT);
    });
});
