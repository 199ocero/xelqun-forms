<?php

namespace Xelqun\Forms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Creates the two halves of a field: its PHP class and its Vue renderer.
 */
class MakeFieldCommand extends Command
{
    private const string CLASS_NAME = '/^[A-Z][A-Za-z0-9]*$/';

    private const string TYPE = '/^[a-z0-9-]+\/[a-z0-9-]+$/';

    protected $signature = 'make:xelqun-field
        {name : The class name, e.g. Rating}
        {--type= : The schema type, e.g. acme/rating (defaults to app/rating)}
        {--search : Search the server as the user types}
        {--uploads : Take file uploads}
        {--force : Overwrite the files if they exist}';

    protected $description = 'Create a Xelqun Forms field: a PHP class and a Vue component';

    public function handle(): int
    {
        $class = (string) $this->argument('name');
        $type = (string) ($this->option('type') ?: 'app/'.Str::kebab($class));

        if (preg_match(self::CLASS_NAME, $class) !== 1) {
            $this->components->error("[{$class}] is not a valid class name.");

            return self::FAILURE;
        }

        if (preg_match(self::TYPE, $type) !== 1) {
            $this->components->error("[{$type}] needs a vendor prefix, like acme/rating. Names without one are reserved for built-in fields.");

            return self::FAILURE;
        }

        if ($this->option('search') && $this->option('uploads')) {
            $this->components->error('Choose either --search or --uploads. The stubs are starting points, and a field can implement both by hand.');

            return self::FAILURE;
        }

        $phpPath = app_path("Forms/Components/{$class}.php");
        $vuePath = resource_path("js/forms/{$class}Field.vue");

        foreach ([$phpPath, $vuePath] as $path) {
            if (File::exists($path) && ! $this->option('force')) {
                $this->components->error("[{$path}] already exists. Use --force to overwrite it.");

                return self::FAILURE;
            }
        }

        $stub = match (true) {
            (bool) $this->option('search') => 'search-field',
            (bool) $this->option('uploads') => 'upload-field',
            default => 'field',
        };

        File::ensureDirectoryExists(dirname($phpPath));
        File::ensureDirectoryExists(dirname($vuePath));
        File::put($phpPath, $this->renderClass($stub, $class, $type));
        File::copy(__DIR__."/../../stubs/{$stub}.vue", $vuePath);

        $this->components->info('Field created.');
        $this->components->bulletList([$phpPath, $vuePath]);
        $this->line("  Register the renderer in createXelqunForms():\n");
        $this->line("    components: { '{$type}': () => import('./forms/{$class}Field.vue') }\n");

        return self::SUCCESS;
    }

    private function renderClass(string $stub, string $class, string $type): string
    {
        return str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ type }}'],
            [$this->laravel->getNamespace().'Forms\\Components', $class, $type],
            File::get(__DIR__."/../../stubs/{$stub}.php.stub"),
        );
    }
}
