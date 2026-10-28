<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Tests\Fixtures\OrderForm;
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
