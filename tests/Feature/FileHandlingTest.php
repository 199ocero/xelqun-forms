<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\Builder;
use Xelqun\Forms\Components\Builder\Block;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Conditions\When;
use Xelqun\Forms\Form;
use Xelqun\Forms\Tests\Fixtures\Order;
use Xelqun\Forms\Uploads\TemporaryUpload;

class ImportForm extends Form
{
    public static ?TemporaryUpload $received = null;

    public function model(): ?string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [
            TextInput::make('customer')->default('Ada'),
            FileUpload::make('meta')->storeFiles(false)->acceptedFileTypes(['text/plain']),
        ];
    }

    public function mutateBeforeSave(array $attributes): array
    {
        self::$received = $attributes['meta'] ?? null;

        return $attributes;
    }
}

function sameSession(): void
{
    request()->setLaravelSession(tap(app('session')->driver())->setId(str_repeat('a', 40)));
}

function uploadForField(string $form, string $path, UploadedFile $file): string
{
    return test()->withCredentials()->withCookie(config('session.cookie'), str_repeat('a', 40))->post(route('xelqun.forms.upload'), [
        'token' => $form::make()->makeToken(),
        'path' => $path,
        'file' => $file,
    ])->assertOk()->json('token');
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    ImportForm::$received = null;
});

describe('storeFiles(false)', function () {
    it('gives back the temporary file, and stores nothing', function () {
        $token = uploadForField(ImportForm::class, 'data.meta', UploadedFile::fake()->createWithContent('a.txt', 'hello'));

        sameSession();

        $file = ImportForm::make()->validate(['data' => ['meta' => $token]])['meta'];

        expect($file)->toBeInstanceOf(TemporaryUpload::class)
            ->and($file->name)->toBe('a.txt')
            ->and($file->get())->toBe('hello')
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('still refuses a token made for something else', function () {
        sameSession();

        ImportForm::make()->validate(['data' => ['meta' => 'xq-tmp:'.str_repeat('b', 40)]]);
    })->throws(ValidationException::class);

    it('leaves it out of the columns on save, after mutateBeforeSave() had it', function () {
        $token = uploadForField(ImportForm::class, 'data.meta', UploadedFile::fake()->createWithContent('a.txt', 'hello'));

        sameSession();

        $order = ImportForm::make()->save(['data' => ['customer' => 'Ada', 'meta' => $token]]);

        expect(ImportForm::$received?->get())->toBe('hello')
            ->and($order->refresh()->meta)->toBeNull();
    });

    it('leaves relationship rows alone', function () {
        expect(FileUpload::make('files')->relationship()->storeFiles(false)->shouldPersistItself())->toBeFalse();
    });

    it('turns into something JSON can carry', function () {
        $token = uploadForField(ImportForm::class, 'data.meta', UploadedFile::fake()->createWithContent('a.txt', 'hello'));

        sameSession();

        expect(json_encode(ImportForm::make()->validate(['data' => ['meta' => $token]])))->toContain('"name":"a.txt"');
    });
});

describe('deleteRemovedFiles()', function () {
    class GalleryForm extends Form
    {
        public static bool $fails = false;

        public function model(): ?string
        {
            return Order::class;
        }

        public function schema(): array
        {
            return [
                TextInput::make('customer'),
                TextInput::make('type'),
                FileUpload::make('company')->disk('public')->deleteRemovedFiles()->visibleWhen(When::eq('type', 'business')),
                FileUpload::make('region_id')->disk('public')->saved(false),
                FileUpload::make('meta')->disk('public')->multiple()->deleteRemovedFiles(),
            ];
        }

        public function afterSave(Model $record, array $data): void
        {
            if (self::$fails) {
                throw new RuntimeException('No.');
            }
        }
    }

    class GalleryOrder extends Order
    {
        protected $table = 'orders';

        protected $casts = ['meta' => 'array'];
    }

    function storedOrder(array $files, string $company = 'c.png'): Order
    {
        foreach ([...$files, $company] as $file) {
            Storage::disk('public')->put($file, 'x');
        }

        return GalleryOrder::query()->create(['customer' => 'Ada', 'type' => 'business', 'company' => $company, 'meta' => $files]);
    }

    beforeEach(fn () => GalleryForm::$fails = false);

    it('deletes a file the record no longer holds, and keeps the rest', function () {
        $order = storedOrder(['a.png', 'b.png']);

        GalleryForm::make($order)->save(['data' => ['customer' => 'Ada', 'type' => 'business', 'company' => 'c.png', 'meta' => ['a.png']]]);

        Storage::disk('public')->assertExists('a.png');
        Storage::disk('public')->assertMissing('b.png');
    });

    it('keeps the file when the save rolls back', function () {
        $order = storedOrder(['a.png', 'b.png']);
        GalleryForm::$fails = true;

        expect(fn () => GalleryForm::make($order)->save(['data' => ['customer' => 'Ada', 'type' => 'business', 'company' => 'c.png', 'meta' => ['a.png']]]))
            ->toThrow(RuntimeException::class);

        Storage::disk('public')->assertExists('b.png');
    });

    it('waits for an outer transaction to commit', function () {
        $order = storedOrder(['a.png', 'b.png']);

        DB::transaction(function () use ($order): void {
            GalleryForm::make($order)->save(['data' => ['customer' => 'Ada', 'type' => 'business', 'company' => 'c.png', 'meta' => ['a.png']]]);

            Storage::disk('public')->assertExists('b.png');
        });

        Storage::disk('public')->assertMissing('b.png');
    });

    it('keeps the files of an outer transaction that rolls back', function () {
        $order = storedOrder(['a.png', 'b.png']);

        try {
            DB::transaction(function () use ($order): void {
                GalleryForm::make($order)->save(['data' => ['customer' => 'Ada', 'type' => 'business', 'company' => 'c.png', 'meta' => ['a.png']]]);

                throw new RuntimeException('No.');
            });
        } catch (RuntimeException) {
        }

        Storage::disk('public')->assertExists('b.png');
    });

    it('keeps the file of a field that was left out, not removed', function () {
        $order = storedOrder(['a.png']);

        GalleryForm::make($order)->save(['data' => ['customer' => 'Ada', 'type' => 'person', 'meta' => ['a.png']]]);

        Storage::disk('public')->assertExists('c.png');
    });

    it('never touches a field that did not ask', function () {
        Storage::disk('public')->put('r.png', 'x');
        $order = storedOrder(['a.png']);
        $order->forceFill(['region_id' => 1])->save();

        GalleryForm::make($order)->save(['data' => ['customer' => 'Ada', 'type' => 'business', 'company' => 'c.png', 'meta' => ['a.png'], 'region_id' => null]]);

        Storage::disk('public')->assertExists('r.png');
    });

    it('deletes nothing on validate(), which saves nothing', function () {
        $order = storedOrder(['a.png', 'b.png']);

        GalleryForm::make($order)->validate(['data' => ['customer' => 'Ada', 'type' => 'business', 'company' => 'c.png', 'meta' => ['a.png']]]);

        Storage::disk('public')->assertExists('b.png');
    });

    it('can not be pointed at a file the record never had', function () {
        Storage::disk('public')->put('other.png', 'x');
        $order = storedOrder(['a.png']);

        expect(fn () => GalleryForm::make($order)->save(['data' => ['customer' => 'Ada', 'type' => 'business', 'company' => 'c.png', 'meta' => ['other.png']]]))
            ->toThrow(ValidationException::class);

        Storage::disk('public')->assertExists('other.png');
        Storage::disk('public')->assertExists('a.png');
    });

    describe('in rows', function () {
        class RowFilesForm extends Form
        {
            public function model(): ?string
            {
                return Order::class;
            }

            public function schema(): array
            {
                return [
                    TextInput::make('customer'),
                    Repeater::make('meta')->schema([
                        TextInput::make('type'),
                        FileUpload::make('photo')->disk('public')->deleteRemovedFiles()->visibleWhen(When::eq('type', 'image')),
                    ]),
                ];
            }
        }

        function orderWithPhotoRows(): Order
        {
            Storage::disk('public')->put('one.png', 'x');
            Storage::disk('public')->put('two.png', 'x');

            return GalleryOrder::query()->create(['customer' => 'Ada', 'meta' => [
                ['type' => 'image', 'photo' => 'one.png'],
                ['type' => 'image', 'photo' => 'two.png'],
            ]]);
        }

        function photoRowsOf(Order $order): array
        {
            return RowFilesForm::make($order)->getData()['meta'];
        }

        it('deletes the file of a row that was deleted', function () {
            $order = orderWithPhotoRows();
            $rows = photoRowsOf($order);
            array_pop($rows);

            RowFilesForm::make($order)->save(['data' => ['customer' => 'Ada', 'meta' => $rows]]);

            Storage::disk('public')->assertExists('one.png');
            Storage::disk('public')->assertMissing('two.png');
        });

        it('deletes the files of every row when all are gone', function () {
            $order = orderWithPhotoRows();

            RowFilesForm::make($order)->save(['data' => ['customer' => 'Ada', 'meta' => []]]);

            Storage::disk('public')->assertMissing('one.png');
            Storage::disk('public')->assertMissing('two.png');
        });

        it('keeps a file that a condition left out of its row', function () {
            $order = orderWithPhotoRows();
            $rows = photoRowsOf($order);
            $key = array_key_first($rows);
            $rows[$key]['type'] = 'text';
            unset($rows[$key]['photo']);

            RowFilesForm::make($order)->save(['data' => ['customer' => 'Ada', 'meta' => $rows]]);

            Storage::disk('public')->assertExists('one.png');
        });
    });
});

describe('deleteRemovedFiles() in a builder', function () {
    class FileBlocksForm extends Form
    {
        public function model(): ?string
        {
            return Order::class;
        }

        public function schema(): array
        {
            return [
                TextInput::make('customer'),
                Builder::make('meta')->blocks([
                    Block::make('temporary')->schema([FileUpload::make('file')->disk('public')->deleteRemovedFiles()]),
                    Block::make('archive')->schema([FileUpload::make('file')->disk('public')]),
                ]),
            ];
        }
    }

    function orderWithBlocks(): Order
    {
        Storage::disk('public')->put('temporary.png', 'x');
        Storage::disk('public')->put('archive.png', 'x');

        return GalleryOrder::query()->create(['customer' => 'Ada', 'meta' => [
            ['type' => 'temporary', 'data' => ['file' => 'temporary.png']],
            ['type' => 'archive', 'data' => ['file' => 'archive.png']],
        ]]);
    }

    it('deletes only for the block that asked', function () {
        FileBlocksForm::make(orderWithBlocks())->save(['data' => ['customer' => 'Ada', 'meta' => []]]);

        Storage::disk('public')->assertMissing('temporary.png');
        Storage::disk('public')->assertExists('archive.png');
    });
});

it('sends the image tools to the browser', function () {
    $upload = new class extends Form
    {
        public function schema(): array
        {
            return [FileUpload::make('photo')->image()->imageEditor()->imageEditorAspectRatioOptions(['1:1'])->automaticallyResizeImagesToWidth(100)];
        }
    };

    $props = $upload::make()->toSchema()['components'][0]['props'];

    expect($props->imageEditor['aspectRatios'])->toBe(['1:1'])
        ->and($props->imageResize)->toMatchArray(['width' => 100, 'upscale' => true]);
});
