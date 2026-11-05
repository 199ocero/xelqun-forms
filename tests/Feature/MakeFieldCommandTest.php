<?php

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Contracts\AcceptsUploads;
use Xelqun\Forms\Form;

beforeEach(function (): void {
    $this->appPath = sys_get_temp_dir().'/xelqun-make-'.uniqid();

    File::ensureDirectoryExists("{$this->appPath}/resources/js");
    File::put("{$this->appPath}/composer.json", json_encode([
        'autoload' => ['psr-4' => ['App\\' => 'app/']],
    ]));

    $this->app->setBasePath($this->appPath);
});

afterEach(function (): void {
    File::deleteDirectory($this->appPath);
});

function generatedClass(string $appPath, string $name): string
{
    $class = 'App\\Forms\\Components\\'.$name;

    if (! class_exists($class, false)) {
        require "{$appPath}/app/Forms/Components/{$name}.php";
    }

    return $class;
}

it('creates a field and its renderer', function (): void {
    $this->artisan('make:xelqun-field', ['name' => 'StarRating'])
        ->expectsOutputToContain("'app/star-rating': () => import('./forms/StarRatingField.vue')")
        ->assertSuccessful();

    $class = generatedClass($this->appPath, 'StarRating');

    expect($this->formNode(Form::make([$class::make('stars')]), 'data.stars')['type'])->toBe('app/star-rating')
        ->and(File::get("{$this->appPath}/resources/js/forms/StarRatingField.vue"))->toBe(File::get(__DIR__.'/../../stubs/field.vue'));
});

it('uses the type it is given', function (): void {
    $this->artisan('make:xelqun-field', ['name' => 'Stars', '--type' => 'acme/stars'])->assertSuccessful();

    $class = generatedClass($this->appPath, 'Stars');

    $this->assertNamespacedField(Form::make([$class::make('a')]), 'data.a');
});

it('creates a field that searches', function (): void {
    $this->artisan('make:xelqun-field', ['name' => 'PersonPicker', '--search' => true])->assertSuccessful();

    $class = generatedClass($this->appPath, 'PersonPicker');
    $form = Form::make([$class::make('person')->options(['ada' => 'Ada', 'alan' => 'Alan'])]);

    expect($form->getComponents()[0]->getSearchResults('ad'))->toBe([['value' => 'ada', 'label' => 'Ada']])
        ->and(fn () => $form->validate(['data' => ['person' => 'grace']]))->toThrow(ValidationException::class)
        ->and($form->validate(['data' => ['person' => 'ada']]))->toBe(['person' => 'ada']);
});

it('creates a field that takes uploads', function (): void {
    $this->artisan('make:xelqun-field', ['name' => 'Receipt', '--uploads' => true])->assertSuccessful();

    $class = generatedClass($this->appPath, 'Receipt');

    expect($class::make('a'))->toBeInstanceOf(AcceptsUploads::class)
        ->and(File::get("{$this->appPath}/resources/js/forms/ReceiptField.vue"))->toContain('store.upload');
});

it('refuses a type without a vendor prefix', function (): void {
    $this->artisan('make:xelqun-field', ['name' => 'Stars', '--type' => 'stars'])
        ->expectsOutputToContain('vendor prefix')
        ->assertFailed();

    expect(File::exists("{$this->appPath}/app/Forms/Components/Stars.php"))->toBeFalse();
});

it('refuses a name that is not a class name', function (string $name): void {
    $this->artisan('make:xelqun-field', ['name' => $name])->assertFailed();
})->with(['rating', '../Evil', 'Sub\\Class', 'Has Space']);

it('refuses search and uploads together', function (): void {
    $this->artisan('make:xelqun-field', ['name' => 'Both', '--search' => true, '--uploads' => true])->assertFailed();
});

it('keeps existing files unless told to overwrite them', function (): void {
    $this->artisan('make:xelqun-field', ['name' => 'Stars'])->assertSuccessful();
    File::put("{$this->appPath}/app/Forms/Components/Stars.php", 'mine');

    $this->artisan('make:xelqun-field', ['name' => 'Stars'])->assertFailed();
    expect(File::get("{$this->appPath}/app/Forms/Components/Stars.php"))->toBe('mine');

    $this->artisan('make:xelqun-field', ['name' => 'Stars', '--force' => true])->assertSuccessful();
    expect(File::get("{$this->appPath}/app/Forms/Components/Stars.php"))->not->toBe('mine');
});
