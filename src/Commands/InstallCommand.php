<?php

namespace Xelqun\Forms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Xelqun\Forms\Commands\Concerns\EditsHostApp;
use Xelqun\Forms\Schema\SchemaVersion;

class InstallCommand extends Command
{
    use EditsHostApp;

    private const string NPM_PACKAGE = '@xelqun/forms';

    private const string STYLESHEET = '@xelqun/forms/style.css';

    private const string PLUGIN_CALL = 'createXelqunForms()';

    /**
     * All three, so installing tables or panels later needs no second edit. A name
     * Vite can't find here is ignored.
     */
    private const string VITE_EXCLUDE = "exclude: ['@xelqun/forms', '@xelqun/tables', '@xelqun/panels'],";

    protected $signature = 'xelqun-forms:install
        {--config : Publish the config file}
        {--without-node : Do not install the npm package}';

    protected $description = 'Install the Xelqun Forms npm package and add it to the app';

    public function handle(): int
    {
        if (! $this->option('without-node') && ! $this->installNodePackage(self::NPM_PACKAGE.'@'.SchemaVersion::PACKAGE)) {
            return self::FAILURE;
        }

        $manualSteps = array_filter([
            $this->registerPlugin(),
            $this->importStylesheet(self::STYLESHEET),
            $this->excludeFromPreBundling(),
        ]);

        if ($this->option('config')) {
            $this->callSilently('vendor:publish', ['--tag' => 'xelqun-forms-config']);
        }

        if ($manualSteps !== []) {
            $this->components->warn('Finish these steps by hand:');
            $this->components->bulletList($manualSteps);

            return self::SUCCESS;
        }

        $this->components->info('Xelqun Forms is installed.');

        return self::SUCCESS;
    }

    /**
     * @return string|null The step left for the developer, or null when none remains.
     */
    private function registerPlugin(): ?string
    {
        $manualStep = "Call `app.use(createXelqunForms())` in `withApp` in your Inertia entry file, importing it from '".self::NPM_PACKAGE."'. Forms still render without it, but custom components won't be registered.";

        $path = $this->findEntryFile();

        if ($path === null) {
            return $manualStep;
        }

        $source = File::get($path);

        if (str_contains($source, 'createXelqunForms')) {
            return null;
        }

        $patched = $this->addPluginCall($source);

        if ($patched === null) {
            return $manualStep;
        }

        File::put($path, $this->addImport($patched, "import { createXelqunForms } from '".self::NPM_PACKAGE."';"));

        return null;
    }

    /**
     * Vite pre-bundles the packages' TypeScript but can't bundle their .vue files, so in
     * dev mode those files read a second copy of shared state, such as the icon registry.
     *
     * @return string|null The step left for the developer, or null when none remains.
     */
    private function excludeFromPreBundling(): ?string
    {
        $manualStep = 'Add `optimizeDeps: { '.self::VITE_EXCLUDE.' }` to your Vite config. Without it, icons and other shared state break in dev mode.';

        $path = array_find([base_path('vite.config.ts'), base_path('vite.config.js')], fn (string $path): bool => File::exists($path));

        if ($path === null) {
            return $manualStep;
        }

        $source = File::get($path);

        if (str_contains($source, 'optimizeDeps')) {
            return str_contains($source, self::NPM_PACKAGE) ? null : $manualStep;
        }

        $patched = preg_replace_callback('/defineConfig\(\{[ \t]*\r?\n([ \t]+)/', fn (array $match): string => sprintf(
            "%s// Pre-bundling these would split them from their .vue files.\n%soptimizeDeps: {\n%s%s%s\n%s},\n%s",
            $match[0],
            $match[1],
            $match[1],
            $match[1],
            self::VITE_EXCLUDE,
            $match[1],
            $match[1],
        ), $source, 1, $replacements);

        if ($replacements === 0) {
            return $manualStep;
        }

        File::put($path, $patched);

        return null;
    }

    /**
     * Only rewrites shapes it can read with confidence. Anything else returns null,
     * because a wrong edit to the app's entry file is worse than a printed instruction.
     */
    private function addPluginCall(string $source): ?string
    {
        $withAppBlock = '/withApp\s*(?::\s*\(?|\()\s*(?!function\b)(\w+)[^)\n]*\)?\s*(?:=>\s*)?\{[ \t]*\r?\n([ \t]+)/';

        if (preg_match($withAppBlock, $source)) {
            return preg_replace_callback($withAppBlock, fn (array $match): string => sprintf(
                '%s%s.use(%s);%s%s',
                $match[0],
                $match[1],
                self::PLUGIN_CALL,
                "\n",
                $match[2],
            ), $source, 1);
        }

        if (str_contains($source, 'withApp')) {
            return null;
        }

        $inertiaPlugin = '/(?:^([ \t]+))?\.use\(plugin\)/m';

        if (preg_match($inertiaPlugin, $source)) {
            return preg_replace_callback($inertiaPlugin, function (array $match): string {
                $separator = ($match[1] ?? '') === '' ? '' : "\n{$match[1]}";

                return $match[0].$separator.'.use('.self::PLUGIN_CALL.')';
            }, $source, 1);
        }

        if (preg_match('/\bsetup\s*[(:]/', $source)) {
            return null;
        }

        $inertiaOptions = '/createInertiaApp\(\{[ \t]*\r?\n([ \t]+)/';

        if (preg_match($inertiaOptions, $source)) {
            return preg_replace_callback($inertiaOptions, fn (array $match): string => sprintf(
                "%swithApp: (app) => {\n%s%sapp.use(%s);\n%s},\n%s",
                $match[0],
                $match[1],
                $match[1],
                self::PLUGIN_CALL,
                $match[1],
                $match[1],
            ), $source, 1);
        }

        return null;
    }
}
