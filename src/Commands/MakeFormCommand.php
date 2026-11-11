<?php

namespace Xelqun\Forms\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MakeFormCommand extends Command
{
    private const string CLASS_NAME = '/^[A-Z][A-Za-z0-9]*$/';

    private const array FIRST_COLUMNS = ['name', 'title', 'label', 'number', 'email', 'slug'];

    protected $signature = 'make:xelqun-form
        {name : The class name, e.g. OrderForm}
        {--model= : The model it edits (defaults to the name without "Form", in the singular)}
        {--force : Overwrite the file if it exists}';

    protected $description = 'Create a Xelqun Forms form class';

    public function handle(): int
    {
        $class = (string) $this->argument('name');
        $model = (string) ($this->option('model') ?: Str::singular(Str::beforeLast($class, 'Form')));

        foreach ([$class, $model] as $name) {
            if (preg_match(self::CLASS_NAME, $name) !== 1) {
                $this->components->error("[{$name}] is not a valid class name.");

                return self::FAILURE;
            }
        }

        $path = app_path("Forms/{$class}.php");

        if (File::exists($path) && ! $this->option('force')) {
            $this->components->error("[{$path}] already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        $modelNamespace = $this->laravel->getNamespace().'Models';
        $modelClass = "{$modelNamespace}\\{$model}";

        File::ensureDirectoryExists(dirname($path));
        File::put($path, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ modelNamespace }}', '{{ model }}', '{{ column }}'],
            [$this->laravel->getNamespace().'Forms', $class, $modelNamespace, $model, $this->guessFirstColumn(is_subclass_of($modelClass, Model::class) ? new $modelClass : null)],
            File::get(__DIR__.'/../../stubs/form.php.stub'),
        ));

        $this->components->info('Form created.');
        $this->components->bulletList([$path]);

        return self::SUCCESS;
    }

    /**
     * The form starts from a column the table really has, so it works before
     * anyone edits it. Without a table to read, it uses `name`. Panels' resource
     * generator makes the same guess, but forms can't depend on panels.
     */
    private function guessFirstColumn(?Model $model): string
    {
        try {
            $columns = $model === null ? [] : Schema::getColumns($model->getTable());
        } catch (Throwable) {
            $columns = [];
        }

        $names = array_column($columns, 'name');
        $text = array_column(array_filter($columns, fn (array $column): bool => preg_match('/char|text|string/i', (string) $column['type_name']) === 1), 'name');

        return array_find(self::FIRST_COLUMNS, fn (string $name): bool => in_array($name, $names, true)) ?? $text[0] ?? 'name';
    }
}
