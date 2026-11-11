<?php

use App\Forms\OrderForm;
use App\Models\Order;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->appPath = sys_get_temp_dir().'/xelqun-make-form-'.uniqid();

    File::ensureDirectoryExists($this->appPath);
    File::put("{$this->appPath}/composer.json", json_encode([
        'autoload' => ['psr-4' => ['App\\' => 'app/']],
    ]));

    $this->originalBasePath = $this->app->basePath();
    $this->app->setBasePath($this->appPath);
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->appPath);
});

it('creates a form for the model named after it', function (): void {
    eval('namespace App\\Models; class Order extends \\Illuminate\\Database\\Eloquent\\Model {}');

    $this->artisan('make:xelqun-form', ['name' => 'OrderForm'])
        ->expectsOutputToContain('Form created.')
        ->assertSuccessful();

    require "{$this->appPath}/app/Forms/OrderForm.php";
    $form = OrderForm::make();

    // orders has no name or title, so it starts from its first text column.
    expect($form->model())->toBe(Order::class)
        ->and($this->formNode($form, 'data.customer')['type'])->toBe('text-input');
});

it('uses the model it is given, and a column that table has', function (): void {
    eval('namespace App\\Models; class Tag extends \\Illuminate\\Database\\Eloquent\\Model {}');

    $this->artisan('make:xelqun-form', ['name' => 'LabelForm', '--model' => 'Tag'])->assertSuccessful();

    expect(File::get("{$this->appPath}/app/Forms/LabelForm.php"))
        ->toContain('return Tag::class;')
        ->toContain("TextInput::make('name')");
});

it('falls back to name without a table to read', function (): void {
    $this->artisan('make:xelqun-form', ['name' => 'GhostForm'])->assertSuccessful();

    expect(File::get("{$this->appPath}/app/Forms/GhostForm.php"))
        ->toContain('use App\Models\Ghost;')
        ->toContain("TextInput::make('name')");
});

it('refuses a name that is not a class name', function (array $arguments): void {
    $this->artisan('make:xelqun-form', $arguments)->assertFailed();

    expect(File::isDirectory("{$this->appPath}/app/Forms"))->toBeFalse();
})->with([
    [['name' => 'orderForm']],
    [['name' => '../EvilForm']],
    [['name' => 'OrderForm', '--model' => 'Sub\\Order']],
]);

it('keeps an existing file unless told to overwrite it', function (): void {
    $this->artisan('make:xelqun-form', ['name' => 'GhostForm'])->assertSuccessful();
    File::put("{$this->appPath}/app/Forms/GhostForm.php", 'mine');

    $this->artisan('make:xelqun-form', ['name' => 'GhostForm'])->assertFailed();
    expect(File::get("{$this->appPath}/app/Forms/GhostForm.php"))->toBe('mine');

    $this->artisan('make:xelqun-form', ['name' => 'GhostForm', '--force' => true])->assertSuccessful();
    expect(File::get("{$this->appPath}/app/Forms/GhostForm.php"))->not->toBe('mine');
});
