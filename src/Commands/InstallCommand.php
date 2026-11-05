<?php

namespace Xelqun\Forms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Xelqun\Forms\Schema\SchemaVersion;

class InstallCommand extends Command
{
    private const string NPM_PACKAGE = '@xelqun/forms';

    private const string STYLESHEET = '@xelqun/forms/style.css';

    private const string PLUGIN_CALL = 'createXelqunForms()';

    private const int NODE_TIMEOUT_SECONDS = 600;

    protected $signature = 'xelqun-forms:install
        {--config : Publish the config file}
        {--without-node : Do not install the npm package}';

    protected $description = 'Install the Xelqun Forms npm package and add it to the app';

    public function handle(): int
    {
        if (! $this->option('without-node') && ! $this->installNodePackage()) {
            return self::FAILURE;
        }

        $manualSteps = array_filter([$this->registerPlugin(), $this->importStylesheet()]);

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
     * Runs before any file is edited, so a failed install never leaves the app
     * importing a package it does not have.
     */
    private function installNodePackage(): bool
    {
        if (! File::exists(base_path('package.json'))) {
            $this->components->error('No package.json found. Xelqun Forms needs an app that builds its frontend with Vite.');

            return false;
        }

        $command = $this->nodeInstallCommand().' '.self::NPM_PACKAGE.'@'.SchemaVersion::PACKAGE;

        $this->components->info("Running: {$command}");

        $result = Process::path(base_path())
            ->timeout(self::NODE_TIMEOUT_SECONDS)
            ->run($command, function (string $type, string $output): void {
                $this->output->write($output);
            });

        if ($result->failed()) {
            $this->components->error("Could not install the npm package. Run `{$command}` yourself, then run this command again with --without-node.");

            return false;
        }

        return true;
    }

    private function nodeInstallCommand(): string
    {
        return match (true) {
            File::exists(base_path('pnpm-lock.yaml')) => 'pnpm add',
            File::exists(base_path('yarn.lock')) => 'yarn add',
            File::exists(base_path('bun.lock')), File::exists(base_path('bun.lockb')) => 'bun add',
            default => 'npm install',
        };
    }

    /**
     * @return string|null The step left for the developer, or null when none remains.
     */
    private function registerPlugin(): ?string
    {
        $manualStep = "Call `app.use(createXelqunForms())` in `withApp` in your Inertia entry file, importing it from '".self::NPM_PACKAGE."'. Forms still render without it, but custom components won't be registered.";

        $path = $this->firstExistingPath([resource_path('js/app.ts'), resource_path('js/app.js')]);

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

        File::put($path, $this->addPluginImport($patched));

        return null;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function firstExistingPath(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

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

    private function addPluginImport(string $source): string
    {
        $import = "import { createXelqunForms } from '".self::NPM_PACKAGE."';";

        preg_match_all(
            '/^import\s(?:[^;\'"]*?\sfrom\s+)?[\'"][^\'"]+[\'"];?[ \t]*$/m',
            $source,
            $imports,
            PREG_OFFSET_CAPTURE,
        );

        if ($imports[0] === []) {
            return "{$import}\n{$source}";
        }

        [$lastImport, $offset] = end($imports[0]);

        return substr_replace($source, "\n{$import}", $offset + strlen($lastImport), 0);
    }

    /**
     * @return string|null The step left for the developer, or null when none remains.
     */
    private function importStylesheet(): ?string
    {
        $manualStep = "Add `@import '".self::STYLESHEET."';` after `@import 'tailwindcss';` in your Tailwind entry file.";

        $path = resource_path('css/app.css');

        if (! File::exists($path)) {
            return $manualStep;
        }

        $source = File::get($path);

        if (str_contains($source, self::STYLESHEET)) {
            return null;
        }

        $patched = preg_replace(
            '/^@import\s+([\'"])tailwindcss\1[^;\n]*;[ \t]*$/m',
            '$0'."\n".'@import $1'.self::STYLESHEET.'$1;',
            $source,
            1,
            $replacements,
        );

        if ($replacements === 0) {
            return $manualStep;
        }

        File::put($path, $patched);

        return null;
    }
}
