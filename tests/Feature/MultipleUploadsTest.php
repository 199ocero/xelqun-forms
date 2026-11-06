<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\Tests\Fixtures\Order;
use Xelqun\Forms\Tests\Fixtures\OrderFile;
use Xelqun\Forms\Tests\Fixtures\OrderForm;
use Xelqun\Forms\Uploads\TemporaryUploadStore;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function fileForm(Closure $schema): OrderForm
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
 * @return array<string, array<int, string>>
 */
function fileErrors(Form $form, array $data): array
{
    try {
        $form->validate($data);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

/**
 * Uploads belong to a session, which test requests don't share, so this uses the
 * store directly. UploadTest covers the endpoint.
 */
function uploadTo(object $test, Form $form, string $path, string $name = 'a.pdf', int $kilobytes = 1): string
{
    $file = UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');

    return app(TemporaryUploadStore::class)->store($file, $form->findComponent($path)->getUploadScope())['token'];
}

function fileProps(Form $form, int $index = 1): array
{
    return (array) $form->toSchema()['components'][$index]['props'];
}

describe('multiple', function () {
    it('stores every file and keeps their order', function () {
        $form = fileForm(fn () => [FileUpload::make('meta')->multiple()->disk('public')]);
        $make = $form::make();
        $first = uploadTo($this, $make, 'data.meta', 'first.pdf');
        $second = uploadTo($this, $make, 'data.meta', 'second.pdf');

        $order = $form::make()->save(['customer' => 'Ada', 'meta' => [$first, $second]]);
        $paths = $order->fresh()->meta;

        expect($paths)->toHaveCount(2)
            ->and($paths[0])->toStartWith('first-')
            ->and($paths[1])->toStartWith('second-');

        Storage::disk('public')->assertExists($paths);
        expect(Storage::disk('local')->allFiles('xelqun-tmp'))->toBe([]);
    });

    it('fills a list, describes each file, and starts empty', function () {
        $form = fileForm(fn () => [FileUpload::make('meta')->multiple()->disk('public')]);
        $order = Order::query()->create(['customer' => 'Ada', 'meta' => ['a.pdf', 'b.pdf']]);
        $filled = $form::make($order);

        expect($filled->getData()['meta'])->toBe(['a.pdf', 'b.pdf'])
            ->and(array_keys((array) fileProps($filled)['files']))->toBe(['a.pdf', 'b.pdf'])
            ->and($form::make()->getData()['meta'])->toBe([]);
    });

    it('mixes stored files with new ones, in the posted order', function () {
        $form = fileForm(fn () => [FileUpload::make('meta')->multiple()->disk('public')]);
        $order = Order::query()->create(['customer' => 'Ada', 'meta' => ['kept.pdf', 'dropped.pdf']]);
        $token = uploadTo($this, $form::make($order), 'data.meta', 'new.pdf');

        $form::make($order)->save(['customer' => 'Ada', 'meta' => [$token, 'kept.pdf']]);

        $paths = $order->fresh()->meta;

        expect($paths)->toHaveCount(2)
            ->and($paths[0])->toStartWith('new-')
            ->and($paths[1])->toBe('kept.pdf');
    });

    it('refuses a path the record never had, even next to real ones', function () {
        $form = fileForm(fn () => [FileUpload::make('meta')->multiple()]);
        $order = Order::query()->create(['customer' => 'Ada', 'meta' => ['kept.pdf']]);

        expect(fileErrors($form::make($order), ['customer' => 'Ada', 'meta' => ['kept.pdf', 'secret/other-user.pdf']]))
            ->toHaveKey('data.meta');
    });

    it('refuses a token made for another field', function () {
        $form = fileForm(fn () => [
            FileUpload::make('meta')->multiple(),
            FileUpload::make('company')->disk('public'),
        ]);
        $token = uploadTo($this, $form::make(), 'data.company');

        expect(fileErrors($form::make(), ['customer' => 'Ada', 'meta' => [$token]]))->toHaveKey('data.meta');
    });

    it('refuses something that is not a list of paths', function (mixed $value) {
        $form = fileForm(fn () => [FileUpload::make('meta')->multiple()]);

        expect(fileErrors($form::make(), ['customer' => 'Ada', 'meta' => $value]))->toHaveKey('data.meta');
    })->with([
        'nested' => [[['a.pdf']]],
        'a number' => [[5]],
        'a path' => ['a.pdf'],
    ]);

    it('counts a file posted twice once, and drops blanks', function () {
        $form = fileForm(fn () => [FileUpload::make('meta')->multiple()->disk('public')]);
        $token = uploadTo($this, $form::make(), 'data.meta');

        $order = $form::make()->save(['customer' => 'Ada', 'meta' => [$token, $token, '', null]]);

        expect($order->fresh()->meta)->toHaveCount(1);
    });

    it('stores nothing when a later file in the list fails', function () {
        $form = fileForm(fn () => [FileUpload::make('meta')->multiple()->disk('public')]);
        $good = uploadTo($this, $form::make(), 'data.meta');
        $bad = uploadTo($this, $form::make(), 'data.meta', 'b.pdf');

        Storage::disk('local')->delete('xelqun-tmp/'.substr($bad, strlen('xq-tmp:')).'.json');

        expect(fn () => $form::make()->save(['customer' => 'Ada', 'meta' => [$good, $bad]]))->toThrow(ValidationException::class);

        expect(Storage::disk('public')->allFiles())->toBe([])
            ->and(Order::query()->count())->toBe(0);
    });

    it('works in a repeater row, where a sibling row owns the path', function () {
        $form = fileForm(fn () => [
            Repeater::make('meta')->schema([FileUpload::make('photos')->multiple()->disk('public')]),
        ]);
        $order = Order::query()->create(['customer' => 'Ada', 'meta' => [['photos' => ['a.pdf']], ['photos' => ['b.pdf', 'c.pdf']]]]);
        $filled = $form::make($order)->getData()['meta'];
        $keys = array_keys($filled);

        expect($filled[$keys[1]]['photos'])->toBe(['b.pdf', 'c.pdf'])
            ->and(fileErrors($form::make($order), ['customer' => 'Ada', 'meta' => [
                $keys[0] => ['photos' => ['a.pdf']],
                $keys[1] => ['photos' => ['b.pdf', 'forged.pdf']],
            ]]))->toHaveKey("data.meta.{$keys[1]}.photos");
    });
});

describe('limits', function () {
    it('refuses too few and too many', function () {
        $form = fileForm(fn () => [FileUpload::make('meta')->multiple()->minFiles(2)->maxFiles(3)->disk('public')]);
        $order = Order::query()->create(['customer' => 'Ada', 'meta' => ['a.pdf', 'b.pdf', 'c.pdf', 'd.pdf']]);

        expect(fileErrors($form::make($order), ['customer' => 'Ada', 'meta' => ['a.pdf']]))->toHaveKey('data.meta')
            ->and(fileErrors($form::make($order), ['customer' => 'Ada', 'meta' => ['a.pdf', 'b.pdf', 'c.pdf', 'd.pdf']]))->toHaveKey('data.meta')
            ->and(fileErrors($form::make($order), ['customer' => 'Ada', 'meta' => ['a.pdf', 'b.pdf']]))->toBe([]);
    });

    it('checks the size of a new file', function () {
        $form = fileForm(fn () => [FileUpload::make('company')->minSize(10)->maxSize(20)]);

        $this->uploadToForm($form::make(), 'data.company', UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'))->assertUnprocessable();
        $this->uploadToForm($form::make(), 'data.company', UploadedFile::fake()->create('a.pdf', 30, 'application/pdf'))->assertUnprocessable();
        $this->uploadToForm($form::make(), 'data.company', UploadedFile::fake()->create('a.pdf', 15, 'application/pdf'))->assertOk();
    });
});

describe('relationship', function () {
    beforeEach(function () {
        $this->form = fileForm(fn () => [FileUpload::make('files')->relationship(orderColumn: 'sort')->disk('public')]);
    });

    it('saves a row per file, in order', function () {
        $make = $this->form::make();
        $first = uploadTo($this, $make, 'data.files', 'first.pdf');
        $second = uploadTo($this, $make, 'data.files', 'second.pdf');

        $order = $this->form::make()->save(['customer' => 'Ada', 'files' => [$first, $second]]);
        $rows = $order->files()->orderBy('sort')->get();

        expect($rows)->toHaveCount(2)
            ->and($rows[0]->path)->toStartWith('first-')
            ->and($rows[0]->sort)->toBe(0)
            ->and($rows[1]->path)->toStartWith('second-')
            ->and($rows[1]->sort)->toBe(1);
    });

    it('fills from the rows in order, and reorders without new rows', function () {
        $order = Order::query()->create(['customer' => 'Ada']);
        $a = $order->files()->create(['path' => 'a.pdf', 'sort' => 1, 'caption' => 'Keep me']);
        $b = $order->files()->create(['path' => 'b.pdf', 'sort' => 0]);

        expect($this->form::make($order)->getData()['files'])->toBe(['b.pdf', 'a.pdf']);

        $this->form::make($order)->save(['customer' => 'Ada', 'files' => ['a.pdf', 'b.pdf']]);

        expect(OrderFile::query()->count())->toBe(2)
            ->and($a->fresh()->sort)->toBe(0)
            ->and($a->fresh()->caption)->toBe('Keep me')
            ->and($b->fresh()->sort)->toBe(1);
    });

    it('deletes the row of a removed file, and only when the field was sent', function () {
        $order = Order::query()->create(['customer' => 'Ada']);
        $order->files()->create(['path' => 'a.pdf']);
        $order->files()->create(['path' => 'b.pdf']);

        $this->form::make($order)->save(['customer' => 'Ada', 'files' => ['b.pdf']]);

        expect($order->files()->pluck('path')->all())->toBe(['b.pdf']);

        $hidden = fileForm(fn () => [FileUpload::make('files')->relationship()->hidden(fn (): bool => true)]);
        $hidden::make($order)->save(['customer' => 'Ada', 'files' => []]);

        expect($order->files()->count())->toBe(1);
    });

    it('can not claim a file of another record', function () {
        $victim = Order::query()->create(['customer' => 'Victim']);
        $victim->files()->create(['path' => 'private.pdf']);
        $order = Order::query()->create(['customer' => 'Ada']);

        expect(fileErrors($this->form::make($order), ['customer' => 'Ada', 'files' => ['private.pdf']]))->toHaveKey('data.files');
    });

    it('works in a related row', function () {
        $form = fileForm(fn () => [
            Repeater::make('items')->relationship()->schema([
                TextInput::make('product'),
                TextInput::make('qty')->numeric()->default(1),
                TextInput::make('price')->numeric()->default(1),
                TextInput::make('subtotal')->numeric()->default(1),
                FileUpload::make('files')->relationship(orderColumn: 'sort')->disk('public'),
            ]),
        ]);
        $token = uploadTo($this, $form::make(), 'data.items.a.files');

        $order = $form::make()->save(['customer' => 'Ada', 'items' => ['a' => ['product' => 'Desk', 'qty' => 1, 'price' => 1, 'subtotal' => 1, 'files' => [$token]]]]);
        $item = $order->items()->sole();

        expect($item->files()->count())->toBe(1);

        $rows = $form::make($order->fresh())->getData()['items'];

        expect($rows[Repeater::RECORD_KEY_PREFIX.$item->id]['files'])->toBe([$item->files()->sole()->path]);
    });

    it('works with a MorphMany', function () {
        $form = fileForm(fn () => [FileUpload::make('attachments')->relationship(orderColumn: 'sort')->disk('public')]);
        $make = $form::make();
        $order = $form::make()->save(['customer' => 'Ada', 'attachments' => [uploadTo($this, $make, 'data.attachments')]]);
        $path = $order->attachments()->sole()->path;

        expect($order->attachments()->sole()->attachable_type)->toBe($order->getMorphClass())
            ->and($form::make($order)->getData()['attachments'])->toBe([$path]);

        $form::make($order)->save(['customer' => 'Ada', 'attachments' => []]);

        expect($order->attachments()->count())->toBe(0);
    });

    it('needs a HasMany', function () {
        $form = fileForm(fn () => [FileUpload::make('note')->relationship()]);

        expect(fn () => $form::make(Order::query()->create(['customer' => 'Ada'])))->toThrow(LogicException::class, 'must be a HasMany');
    });
});

describe('deletable', function () {
    it('brings back a stored file that was removed, and keeps new ones', function () {
        $order = Order::query()->create(['customer' => 'Ada', 'meta' => ['a.pdf', 'b.pdf']]);
        $form = fileForm(fn () => [FileUpload::make('meta')->multiple()->deletable(false)->disk('public')]);
        $token = uploadTo($this, $form::make($order), 'data.meta');

        $form::make($order)->save(['customer' => 'Ada', 'meta' => ['b.pdf', $token]]);

        $meta = $order->fresh()->meta;

        expect($meta)->toHaveCount(3)
            ->and($meta[0])->toBe('b.pdf')
            ->and($meta[2])->toBe('a.pdf');
    });

    it('stops a stored single file being cleared or replaced', function () {
        $order = Order::query()->create(['customer' => 'Ada', 'company' => 'old.pdf']);
        $form = fileForm(fn () => [FileUpload::make('company')->deletable(false)->disk('public')]);

        $form::make($order)->save(['customer' => 'Ada', 'company' => null]);
        expect($order->fresh()->company)->toBe('old.pdf');

        $form::make($order)->save(['customer' => 'Ada', 'company' => uploadTo($this, $form::make($order), 'data.company')]);
        expect($order->fresh()->company)->toBe('old.pdf');
    });

    it('keeps the rows of a relationship', function () {
        $order = Order::query()->create(['customer' => 'Ada']);
        $order->files()->create(['path' => 'a.pdf']);
        $form = fileForm(fn () => [FileUpload::make('files')->relationship()->deletable(fn (): bool => false)]);

        $form::make($order)->save(['customer' => 'Ada', 'files' => []]);

        expect($order->files()->pluck('path')->all())->toBe(['a.pdf']);
    });

    it('protects a file in a saved row', function () {
        $order = Order::query()->create(['customer' => 'Ada']);
        $item = $order->items()->create(['product' => 'Desk', 'qty' => 1, 'price' => 1, 'subtotal' => 1]);
        $item->files()->create(['path' => 'a.pdf']);
        $form = fileForm(fn () => [
            Repeater::make('items')->relationship()->schema([
                TextInput::make('product'),
                TextInput::make('qty')->numeric(),
                TextInput::make('price')->numeric(),
                TextInput::make('subtotal')->numeric(),
                FileUpload::make('files')->relationship()->deletable(false),
            ]),
        ]);
        $rows = $form::make($order)->getData()['items'];
        $rows["record-{$item->id}"]['files'] = [];

        $form::make($order)->save(['customer' => 'Ada', 'items' => $rows]);

        expect($item->files()->pluck('path')->all())->toBe(['a.pdf']);
    });

    it('lets a form with no stored files save, and refuses a list of lists', function () {
        $form = fileForm(fn () => [FileUpload::make('meta')->multiple()->deletable(false)->disk('public')]);

        expect($form::make()->save(['customer' => 'Ada', 'meta' => []])->fresh()->meta)->toBe([])
            ->and(fileErrors($form::make(Order::query()->create(['customer' => 'Ada', 'meta' => ['a.pdf']])), ['meta' => [['x']]]))->toHaveKey('data.meta');
    });
});

it('sends its display options only when used', function () {
    $form = fileForm(fn () => [FileUpload::make('company')]);

    expect(fileProps($form::make()))->not->toHaveKeys(['multiple', 'reorderable', 'downloadable', 'openable', 'avatar', 'pasteable', 'maxParallelUploads']);

    $form = fileForm(fn () => [
        FileUpload::make('company')->multiple()->minSize(2)->reorderable()->downloadable()->openable()->pasteable(false)->maxParallelUploads(1),
    ]);

    expect(fileProps($form::make()))->toMatchArray([
        'multiple' => true,
        'minSize' => 2,
        'reorderable' => true,
        'downloadable' => true,
        'openable' => true,
        'pasteable' => false,
        'maxParallelUploads' => 1,
    ]);
});

it('sends flags that are off, and closures either way', function () {
    expect(fileProps(fileForm(fn () => [FileUpload::make('company')])::make()))->not->toHaveKeys(['deletable', 'previewable', 'appendFiles'])
        ->and(fileProps(fileForm(fn () => [FileUpload::make('company')->deletable(false)->previewable(false)->appendFiles(false)])::make()))
        ->toMatchArray(['deletable' => false, 'previewable' => false, 'appendFiles' => false])
        ->and(fileProps(fileForm(fn () => [FileUpload::make('company')->deletable(fn (): bool => true)])::make()))
        ->toMatchArray(['deletable' => true]);
});

it('takes a closure for each option, and sends it when it comes out false', function () {
    $on = fn (): bool => true;
    $off = fn (): bool => false;
    $form = fileForm(fn () => [FileUpload::make('company')->reorderable($on)->openable($on)->downloadable($off)->pasteable($off)->avatar($off)]);

    expect(fileProps($form::make()))->toMatchArray([
        'reorderable' => true,
        'openable' => true,
        'downloadable' => false,
        'pasteable' => false,
        'avatar' => false,
        'image' => false,
    ]);
});

it('reads a multiple() closure on the server', function () {
    $form = fileForm(fn () => [FileUpload::make('meta')->multiple(fn (): bool => true)->disk('public')]);

    expect($form::make()->getData()['meta'])->toBe([])
        ->and(fileErrors($form::make(), ['meta' => 'nope.pdf']))->toHaveKey('data.meta');

    $order = $form::make()->save(['customer' => 'Ada', 'meta' => [uploadTo($this, $form::make(), 'data.meta')]]);

    expect($order->fresh()->meta)->toHaveCount(1);
});

it('reads an avatar() closure on the server', function (bool $isAvatar) {
    $form = fileForm(fn () => [FileUpload::make('company')->avatar(fn (): bool => $isAvatar)]);

    expect(in_array('image', $form::make()->findComponent('data.company')->getUploadRules(), true))->toBe($isAvatar)
        ->and(fileProps($form::make())['image'])->toBe($isAvatar);
})->with([true, false]);

it('makes an avatar take images only', function () {
    $form = fileForm(fn () => [FileUpload::make('company')->avatar()]);

    expect(fileProps($form::make()))->toMatchArray(['avatar' => true, 'image' => true, 'accept' => 'image/*']);
});

it('never gives a private file a public link', function () {
    Storage::shouldReceive('disk')->with('public')->andReturn($disk = Mockery::mock());
    $disk->shouldReceive('temporaryUrl')->once()->andReturn('https://example.com/signed');
    $disk->shouldNotReceive('url');

    $form = fileForm(fn () => [FileUpload::make('company')->visibility('private')->disk('public')]);
    $record = new Order;
    $record->exists = true;
    $record->company = 'secret.pdf';

    expect(((array) fileProps($form::make($record))['files'])['secret.pdf']['url'])->toBe('https://example.com/signed');
});
