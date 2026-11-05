<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\InvalidStreamProvided;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Tests\Fixtures\AfterCommitFailureForm;
use Xelqun\Forms\Tests\Fixtures\BrokenConnectionOrder;
use Xelqun\Forms\Tests\Fixtures\FailingOrderForm;
use Xelqun\Forms\Tests\Fixtures\OrderForm;
use Xelqun\Forms\Tests\Fixtures\OtherConnectionForm;
use Xelqun\Forms\Tests\Fixtures\OtherConnectionOrder;
use Xelqun\Forms\Uploads\TemporaryUploadStore;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->token = OrderForm::make()->toSchema()['token'];
});

it('stores a temporary upload for a field inside a repeater row', function () {
    $response = $this->post('/_xelqun/forms/upload', [
        'token' => $this->token,
        'path' => 'data.items.new-row.attachment',
        'file' => UploadedFile::fake()->create('invoice.pdf', 12, 'application/pdf'),
    ]);

    $response->assertOk()->assertJsonPath('name', 'invoice.pdf');

    expect($response->json('token'))->toStartWith(TemporaryUploadStore::TOKEN_PREFIX);
});

it('refuses uploads for fields that are not file uploads', function () {
    $this->postJson('/_xelqun/forms/upload', [
        'token' => $this->token,
        'path' => 'data.customer',
        'file' => UploadedFile::fake()->create('a.txt', 1),
    ])->assertUnprocessable();
});

it('moves temporary uploads to the final disk on save', function () {
    $token = $this->post('/_xelqun/forms/upload', [
        'token' => $this->token,
        'path' => 'data.items.u1.attachment',
        'file' => UploadedFile::fake()->create('invoice.pdf', 12, 'application/pdf'),
    ])->json('token');

    $order = OrderForm::make()->save(['data' => [
        'customer' => 'Ada', 'type' => 'individual',
        'items' => ['u1' => ['product' => 'Desk', 'qty' => 1, 'price' => 1, 'attachment' => $token]],
    ]]);

    $path = $order->items()->value('attachment');

    expect($path)->toStartWith('items/')->toEndWith('.pdf');
    Storage::disk('public')->assertExists($path);
    expect(Storage::disk('local')->files('xelqun-tmp'))->toBe([]);
});

describe('when saving does not finish', function () {
    beforeEach(function () {
        FailingOrderForm::$shouldFail = false;

        $this->upload = fn (string $form = FailingOrderForm::class): string => $this->post('/_xelqun/forms/upload', [
            'token' => $form::make()->toSchema()['token'],
            'path' => 'data.items.u1.attachment',
            'file' => UploadedFile::fake()->create('invoice.pdf', 12, 'application/pdf'),
        ])->json('token');

        $this->input = fn (string $token): array => ['data' => [
            'customer' => 'Ada', 'type' => 'individual',
            'items' => ['u1' => ['product' => 'Desk', 'qty' => 1, 'price' => 1, 'attachment' => $token]],
        ]];
    });

    it('leaves no stored file behind and keeps the upload usable', function () {
        $input = ($this->input)(($this->upload)());
        FailingOrderForm::$shouldFail = true;

        expect(fn () => FailingOrderForm::make()->save($input))->toThrow(RuntimeException::class);

        expect(Storage::disk('public')->allFiles())->toBe([]);

        FailingOrderForm::$shouldFail = false;
        $path = FailingOrderForm::make()->save($input)->items()->value('attachment');

        Storage::disk('public')->assertExists($path);
        expect(Storage::disk('local')->files('xelqun-tmp'))->toBe([]);
    });

    it('keeps the upload until an outer transaction commits', function () {
        $input = ($this->input)(($this->upload)());

        DB::transaction(function () use ($input): void {
            FailingOrderForm::make()->save($input);

            expect(Storage::disk('local')->files('xelqun-tmp'))->toHaveCount(2);
        });

        expect(Storage::disk('local')->files('xelqun-tmp'))->toBe([]);
    });

    it('undoes the stored file when an outer transaction rolls back', function () {
        $input = ($this->input)(($this->upload)());

        expect(fn () => DB::transaction(function () use ($input): void {
            FailingOrderForm::make()->save($input);

            throw new RuntimeException('A later step failed.');
        }))->toThrow(RuntimeException::class, 'A later step failed.');

        expect(Storage::disk('public')->allFiles())->toBe([])
            ->and(Storage::disk('local')->files('xelqun-tmp'))->toHaveCount(2);
    });

    it('keeps the stored file when something fails after the commit', function () {
        $input = ($this->input)(($this->upload)(AfterCommitFailureForm::class));

        expect(fn () => AfterCommitFailureForm::make()->save($input))
            ->toThrow(RuntimeException::class, 'The queue is down.');

        Storage::disk('public')->assertExists(DB::table('order_items')->value('attachment'));
    });

    it('leaves the file of another save alone when a save fails', function () {
        $input = ($this->input)(($this->upload)());

        DB::transaction(function () use ($input): void {
            FailingOrderForm::make()->save($input);
            FailingOrderForm::$shouldFail = true;

            expect(fn () => FailingOrderForm::make()->save($input))->toThrow(RuntimeException::class);
        });

        Storage::disk('public')->assertExists(DB::table('order_items')->value('attachment'));
    });

    it('deletes the files already stored when a later upload cannot be stored', function () {
        $store = app(TemporaryUploadStore::class);
        $scope = FailingOrderForm::class.'|data.items.*.attachment';
        $working = $store->store(UploadedFile::fake()->create('a.pdf', 1, 'application/pdf'), $scope)['token'];
        $broken = $store->store(UploadedFile::fake()->create('b.pdf', 1, 'application/pdf'), $scope)['token'];

        $input = ($this->input)($working);
        $input['data']['items']['u2'] = ['product' => 'Lamp', 'qty' => 1, 'price' => 1, 'attachment' => $broken];

        Storage::disk('local')->delete('xelqun-tmp/'.$store->idFromToken($broken));

        expect(fn () => FailingOrderForm::make()->save($input))->toThrow(InvalidStreamProvided::class);

        expect(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('undoes the stored file when a transaction around validate() rolls back', function () {
        $input = ($this->input)(($this->upload)());

        expect(fn () => DB::transaction(function () use ($input): void {
            FailingOrderForm::make()->validate($input);

            throw new RuntimeException('Saving by hand failed.');
        }))->toThrow(RuntimeException::class, 'Saving by hand failed.');

        expect(Storage::disk('public')->allFiles())->toBe([])
            ->and(Storage::disk('local')->files('xelqun-tmp'))->toHaveCount(2);
    });

    it('keeps no copies when the transaction never starts', function () {
        config(['database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent/xelqun.sqlite']]);
        OtherConnectionForm::$modelClass = BrokenConnectionOrder::class;
        $store = app(TemporaryUploadStore::class);
        $token = $store->store(UploadedFile::fake()->create('a.pdf', 1, 'application/pdf'), OtherConnectionForm::class.'|data.company')['token'];

        expect(fn () => OtherConnectionForm::make()->save(['customer' => 'Ada', 'company' => $token]))
            ->toThrow(SQLiteDatabaseDoesNotExistException::class);

        expect(Storage::disk('public')->allFiles())->toBe([])
            ->and($store->metadata($store->idFromToken($token)))->not->toBeNull();
    });

    it('follows the connection of the model when a transaction rolls back', function () {
        config(['database.connections.other' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        Schema::connection('other')->create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('customer');
            $table->string('company')->nullable();
            $table->timestamps();
        });
        OtherConnectionForm::$modelClass = OtherConnectionOrder::class;
        $store = app(TemporaryUploadStore::class);
        $token = $store->store(UploadedFile::fake()->create('a.pdf', 1, 'application/pdf'), OtherConnectionForm::class.'|data.company')['token'];

        expect(fn () => DB::connection('other')->transaction(function () use ($token): void {
            OtherConnectionForm::make()->save(['customer' => 'Ada', 'company' => $token]);

            throw new RuntimeException('A later step failed.');
        }))->toThrow(RuntimeException::class, 'A later step failed.');

        expect(Storage::disk('public')->allFiles())->toBe([])
            ->and($store->metadata($store->idFromToken($token)))->not->toBeNull();
    });

    it('stores one file when a token is posted in two rows', function () {
        $token = ($this->upload)();
        $input = ($this->input)($token);
        $input['data']['items']['u2'] = ['product' => 'Lamp', 'qty' => 1, 'price' => 1, 'attachment' => $token];

        $order = FailingOrderForm::make()->save($input);

        expect($order->items()->pluck('attachment')->unique())->toHaveCount(1)
            ->and(Storage::disk('public')->allFiles())->toHaveCount(1);
    });
});

it('fails when the final disk does not accept the file', function () {
    $store = app(TemporaryUploadStore::class);
    $id = $store->idFromToken($store->store(UploadedFile::fake()->create('a.pdf', 1, 'application/pdf'), 'scope')['token']);

    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('writeStream')->andReturn(false);
    Storage::set('broken', $disk);

    expect(fn () => $store->promote($id, 'broken', 'items', 'public'))->toThrow(RuntimeException::class, 'could not be stored');

    expect($store->metadata($id))->not->toBeNull();
});

it('rejects file paths that never belonged to the record', function () {
    OrderForm::make()->validate(['data' => [
        'customer' => 'Ada', 'type' => 'individual',
        'items' => ['u1' => ['product' => 'Desk', 'qty' => 1, 'price' => 1, 'attachment' => '../../.env']],
    ]]);
})->throws(ValidationException::class);

it('applies the field upload rules', function () {
    $form = new class extends OrderForm
    {
        public function schema(): array
        {
            return [FileUpload::make('avatar')->image()->maxSize(1)];
        }
    };
    $token = $form::make()->toSchema()['token'];

    $this->post('/_xelqun/forms/upload', ['token' => $token, 'path' => 'data.avatar', 'file' => UploadedFile::fake()->image('me.png', 4, 4)], ['Accept' => 'application/json'])
        ->assertOk();

    $this->post('/_xelqun/forms/upload', ['token' => $token, 'path' => 'data.avatar', 'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain')], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors(['file' => 'must be an image']);

    $this->post('/_xelqun/forms/upload', ['token' => $token, 'path' => 'data.avatar', 'file' => UploadedFile::fake()->image('big.png', 400, 400)->size(20)], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors(['file' => 'must not be greater than 1 kilobytes']);
});
