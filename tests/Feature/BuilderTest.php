<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Actions\Action;
use Xelqun\Forms\Components\Builder;
use Xelqun\Forms\Components\Builder\Block;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Actions;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\State\Set;
use Xelqun\Forms\Validation\FormValidator;

class PageForm extends Form
{
    public static bool $adminOnly = true;

    public function schema(): array
    {
        return [
            TextInput::make('title'),
            Builder::make('content')
                ->blocks([
                    'Text' => [
                        Block::make('heading')
                            ->maxItems(1)
                            ->schema([TextInput::make('text')->required()->live()->afterStateUpdated(fn (Get $get, Set $set) => $set('slug', strtolower((string) $get('text')))), TextInput::make('slug')]),
                        Block::make('paragraph')->schema([TextInput::make('text')->maxLength(5)]),
                    ],
                    Block::make('divider'),
                    Block::make('image')->schema([FileUpload::make('file')->disk('local')->image()]),
                    Block::make('document')->schema([FileUpload::make('file')->disk('local')->acceptedFileTypes(['text/plain'])]),
                    Block::make('admin')->hidden(fn (): bool => self::$adminOnly)->schema([TextInput::make('secret')]),
                    Block::make('gallery')->schema([
                        Repeater::make('slides')->schema([TextInput::make('caption')]),
                    ]),
                    Block::make('shout')->schema([
                        TextInput::make('words'),
                        Actions::make([
                            Action::make('go')->authorize(true)->action(fn (Get $get, Set $set) => $set('words', strtoupper((string) $get('words')))),
                        ]),
                    ]),
                ]),
        ];
    }
}

beforeEach(function () {
    PageForm::$adminOnly = true;
    Storage::fake('local');
});

function page(array $rows, array $extra = []): array
{
    return PageForm::make()->validate(['data' => ['content' => $rows, ...$extra]]);
}

function builderPost(string $route, array $payload): TestResponse
{
    return test()->postJson(route("xelqun.forms.{$route}"), ['token' => PageForm::make()->makeToken(), ...$payload]);
}

it('keeps each row as type and data, in a plain list', function () {
    $rows = [
        'r1' => ['type' => 'heading', 'data' => ['text' => 'Hi', 'slug' => 'hi']],
        'r2' => ['type' => 'divider', 'data' => []],
        'r3' => ['type' => 'paragraph', 'data' => ['text' => 'Body']],
    ];

    expect(page($rows)['content'])->toEqual([
        ['type' => 'heading', 'data' => ['text' => 'Hi', 'slug' => 'hi']],
        ['type' => 'divider', 'data' => []],
        ['type' => 'paragraph', 'data' => ['text' => 'Body']],
    ]);
});

it('uses the rules of the block each row is', function () {
    expect(fn () => page(['r1' => ['type' => 'heading', 'data' => ['text' => null]]]))->toThrow(ValidationException::class)
        ->and(fn () => page(['r1' => ['type' => 'paragraph', 'data' => ['text' => 'too long']]]))->toThrow(ValidationException::class);

    expect(page(['r1' => ['type' => 'paragraph', 'data' => ['text' => null]]])['content'][0]['data'])->toBe(['text' => null]);
});

it('names the row in an error', function () {
    try {
        page(['r1' => ['type' => 'divider', 'data' => []], 'r2' => ['type' => 'heading', 'data' => ['text' => null]]]);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('data.content.r2.data.text');
    }
});

it('drops what the block does not declare', function () {
    $output = page(['r1' => ['type' => 'paragraph', 'data' => ['text' => 'a', 'admin' => true], 'extra' => 1]]);

    expect($output['content'])->toEqual([['type' => 'paragraph', 'data' => ['text' => 'a']]]);
});

it('refuses a type that is not declared', function (mixed $type) {
    page(['r1' => ['type' => $type, 'data' => ['secret' => 'x']]]);
})->with(['unknown' => 'evil', 'null' => null, 'an array' => [['a']]])->throws(ValidationException::class);

it('refuses a row with no shape at all', function () {
    page(['r1' => 'nonsense']);
})->throws(ValidationException::class);

it('refuses a block the server hides', function () {
    page(['r1' => ['type' => 'admin', 'data' => ['secret' => 'x']]]);
})->throws(ValidationException::class);

it('allows the same block once it shows', function () {
    PageForm::$adminOnly = false;

    expect(page(['r1' => ['type' => 'admin', 'data' => ['secret' => 'x']]])['content'][0]['data'])->toBe(['secret' => 'x']);
});

it('leaves a hidden block out of the picker', function () {
    $names = collect(PageForm::make()->toSchema()['components'][1]['props']->blocks)->pluck('name');

    expect($names)->toContain('heading')->not->toContain('admin');
});

it('enforces a limit on a block', function () {
    expect(fn () => page([
        'r1' => ['type' => 'heading', 'data' => ['text' => 'One']],
        'r2' => ['type' => 'heading', 'data' => ['text' => 'Two']],
    ]))->toThrow(ValidationException::class, 'Heading can only be used 1 time(s).');
});

it('lets blocks share a field name, and refuses a duplicate inside one', function () {
    expect(PageForm::make()->toSchema())->toHaveKey('components');

    $form = new class extends Form
    {
        public function schema(): array
        {
            return [Builder::make('c')->blocks([Block::make('a')->schema([TextInput::make('x'), TextInput::make('x')])])];
        }
    };

    expect(fn () => $form::make())->toThrow(FormDefinitionException::class);
});

it('fills rows from a list and gives them back unchanged', function () {
    $form = new class extends Form
    {
        public function schema(): array
        {
            return [
                Builder::make('content')
                    ->default([['type' => 'text', 'data' => ['body' => 'Hi']], ['type' => 'line']])
                    ->blocks([Block::make('text')->schema([TextInput::make('body')]), Block::make('line')]),
            ];
        }
    };

    $made = $form::make();
    $rows = $made->getData()['content'];

    expect(array_column($rows, 'type'))->toBe(['text', 'line'])
        ->and(array_values($rows)[0]['data'])->toBe(['body' => 'Hi'])
        ->and($form::make()->validate(['data' => ['content' => $rows]])['content'])->toEqual([
            ['type' => 'text', 'data' => ['body' => 'Hi']],
            ['type' => 'line', 'data' => []],
        ]);
});

it('builds each row from its own block', function () {
    $form = new class extends Form
    {
        public function schema(): array
        {
            return [
                Builder::make('content')
                    ->default([['type' => 'text', 'data' => ['body' => 'Hi']]])
                    ->blocks([Block::make('line'), Block::make('text')->schema([TextInput::make('body')])]),
            ];
        }
    };

    $props = $form::make()->toSchema()['components'][0]['props'];
    $key = array_key_first($props->rows);
    $row = $props->rows[$key];
    $template = collect($props->blocks)->firstWhere('name', 'text')['template'];

    expect(collect($row)->pluck('type')->all())->toBe(['hidden', 'block'])
        ->and($row[1]['children'][0]['path'])->toBe("data.content.{$key}.data.body")
        ->and(str_replace('{row}', $key, (string) json_encode($template[1]['key'])))->toBe(json_encode($row[1]['key']));
});

it('sends each block what a new row starts with', function () {
    $form = new class extends Form
    {
        public function schema(): array
        {
            return [Builder::make('content')->blocks([Block::make('text')->schema([TextInput::make('body')->default('Hello')])])];
        }
    };

    expect(json_encode($form::make()->toSchema()['components'][0]['props']->blocks[0]['newRow']))->toBe('{"type":"text","data":{"body":"Hello"}}');
});

it('flattens repeaters inside a block', function () {
    expect(page(['r1' => ['type' => 'gallery', 'data' => ['slides' => ['s1' => ['caption' => 'A']]]]])['content'][0]['data']['slides'])
        ->toBe([['caption' => 'A']]);
});

it('runs live updates inside a block', function () {
    builderPost('update', [
        'seq' => 1,
        'state' => ['data' => ['content' => ['r1' => ['type' => 'heading', 'data' => ['text' => 'HI']]]]],
        'changed' => ['data.content.r1.data.text'],
        'deps' => (object) [],
    ])->assertOk()->assertJsonPath('set', ['data.content.r1.data.slug' => 'hi']);
});

it('runs an action inside a block, on its row', function () {
    builderPost('action', [
        'action' => 'data.content.r1.data#8.1.0',
        'phase' => 'run',
        'state' => ['data' => ['content' => ['r1' => ['type' => 'shout', 'data' => ['words' => 'hey']]]]],
        'deps' => (object) [],
    ])->assertOk()->assertJsonPath('set', ['data.content.r1.data.words' => 'HEY']);
});

describe('uploads', function () {
    function uploadToBlock(string $type, UploadedFile $file): TestResponse
    {
        return test()->withCredentials()->withCookie(config('session.cookie'), str_repeat('a', 40))->post(route('xelqun.forms.upload'), [
            'token' => PageForm::make()->makeToken(),
            'path' => 'data.content.r1.data.file',
            'state' => json_encode(['content' => ['r1' => ['type' => $type, 'data' => []]]]),
            'file' => $file,
        ]);
    }

    it('takes a file for a block the row posts', function () {
        uploadToBlock('image', UploadedFile::fake()->image('a.png'))->assertOk();
    });

    it('checks the rules of that block', function () {
        uploadToBlock('document', UploadedFile::fake()->image('a.png'))->assertSessionHasErrors('file');
    });

    it('refuses a block that is not declared', function () {
        uploadToBlock('evil', UploadedFile::fake()->image('a.png'))->assertSessionHasErrors('file');
    });

    it('keeps an upload to the block it was made for', function () {
        $token = uploadToBlock('image', UploadedFile::fake()->image('a.png'))->assertOk()->json('token');

        $form = PageForm::make();

        expect(fn () => $form->validate(['data' => ['content' => ['r1' => ['type' => 'document', 'data' => ['file' => $token]]]]]))
            ->toThrow(ValidationException::class);
    });

    it('stores an upload in its block', function () {
        $token = uploadToBlock('image', UploadedFile::fake()->image('a.png'))->assertOk()->json('token');

        $this->withCredentials();
        request()->setLaravelSession(tap(app('session')->driver())->setId(str_repeat('a', 40)));

        expect(PageForm::make()->validate(['data' => ['content' => ['r1' => ['type' => 'image', 'data' => ['file' => $token]]]]])['content'][0]['data']['file'])
            ->toBeString()->not->toStartWith('xq');
    });
});

it('renders previews on the server for rows, and tracks what they read', function () {
    $form = new class extends Form
    {
        public function schema(): array
        {
            return [
                Builder::make('content')
                    ->blockPreviews()
                    ->default([['type' => 'text', 'data' => ['body' => '<b>']]])
                    ->blocks([Block::make('text')->preview(fn (array $data): string => '<p>'.e($data['body']).'</p>')->schema([TextInput::make('body')])]),
            ];
        }
    };

    $props = $form::make()->toSchema()['components'][0]['props'];
    $key = array_key_first($props->rows);
    $block = $props->rows[$key][1];

    expect($block['props']->preview)->toBe('<p>&lt;b&gt;</p>')
        ->and($block['deps'])->toContain("data.content.{$key}.data")
        ->and($props->blocks[0]['template'][1]['props']->preview)->toBeNull();
});

it('has no preview unless asked', function () {
    $form = new class extends Form
    {
        public function schema(): array
        {
            return [Builder::make('content')->default([['type' => 'text']])->blocks([Block::make('text')->preview(fn (): string => 'x')])];
        }
    };

    $props = $form::make()->toSchema()['components'][0]['props'];

    expect(collect($props->rows)->first()[1]['props']->preview)->toBeNull();
});

describe('a block the server hides', function () {
    class StoredBlocksForm extends Form
    {
        public static array $seen = [];

        public function schema(): array
        {
            return [
                Builder::make('content')
                    ->default([['type' => 'admin', 'data' => ['secret' => 'keep me', 'rows' => [['a' => 1]]]], ['type' => 'text', 'data' => ['body' => 'a']]])
                    ->blockPreviews()
                    ->blocks([
                        Block::make('admin')->hidden()->schema([
                            TextInput::make('secret'),
                            Repeater::make('rows')->schema([TextInput::make('a')]),
                        ]),
                        Block::make('text')
                            ->preview(function (array $data): string {
                                self::$seen = $data;

                                return 'x';
                            })
                            ->schema([TextInput::make('body')]),
                    ]),
            ];
        }
    }

    /**
     * What the browser would post back for the form it received.
     */
    function postedPage(): array
    {
        return json_decode((string) json_encode(StoredBlocksForm::make()->toSchema()['state']['data']), true);
    }

    it('never shows the values of a stored row', function () {
        expect(json_encode(postedPage()))->not->toContain('keep me');
    });

    it('saves the form, with the stored row as the server has it', function () {
        $output = StoredBlocksForm::make()->validate(['data' => postedPage()]);

        expect($output['content'][0])->toEqual(['type' => 'admin', 'data' => ['secret' => 'keep me', 'rows' => [['a' => 1]]]]);
    });

    it('ignores what the browser posts for that row', function () {
        $posted = postedPage();
        $key = array_key_first($posted['content']);
        $posted['content'][$key]['data'] = ['secret' => 'changed'];

        expect(StoredBlocksForm::make()->validate(['data' => $posted])['content'][0]['data']['secret'])->toBe('keep me');
    });

    it('refuses a new row of it, and a stored row changed to it', function (string $rowKey) {
        $posted = postedPage();
        $key = $rowKey === 'new' ? 'fresh' : array_key_last($posted['content']);
        $posted['content'][$key] = ['type' => 'admin', 'data' => ['secret' => 'mine']];

        StoredBlocksForm::make()->validate(['data' => $posted]);
    })->with(['new', 'stored'])->throws(ValidationException::class);

    it('gives a preview only the fields it declares', function () {
        $form = StoredBlocksForm::make();
        $key = array_key_last($form->getData()['content']);

        app(FormValidator::class)->prepare($form, [
            'content' => [$key => ['type' => 'text', 'data' => ['body' => 'a', '__env' => 'x']]],
        ]);
        $form->toSchema();

        expect(StoredBlocksForm::$seen)->toBe(['body' => 'a']);
    });
});

it('starts with no blocks', function () {
    expect(Form::make([Builder::make('c')])->toSchema()['components'][0]['props']->blocks)->toBe([]);
});

it('takes its fields through blocks()', function () {
    Builder::make('content')->schema([TextInput::make('a')]);
})->throws(LogicException::class, 'blocks()');

it('has no relationship', function () {
    Builder::make('content')->relationship('items');
})->throws(LogicException::class);
