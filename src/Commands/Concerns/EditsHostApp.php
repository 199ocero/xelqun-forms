<?php

namespace Xelqun\Forms\Commands\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Shared steps of commands that change the host app's frontend. Anything they
 * can't do with confidence is left to the developer as a printed step, because
 * a wrong edit to the app's entry file is worse than an instruction.
 */
trait EditsHostApp
{
    /**
     * Runs before any file is edited, so a failed install never leaves the app
     * importing a package it does not have.
     */
    private function installNodePackage(string $package): bool
    {
        if (! File::exists(base_path('package.json'))) {
            $this->components->error('No package.json found. Xelqun needs an app that builds its frontend with Vite.');

            return false;
        }

        $command = $this->nodeInstallCommand().' '.$package;

        $this->components->info("Running: {$command}");

        $result = Process::path(base_path())
            ->timeout(600)
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

    private function findEntryFile(): ?string
    {
        foreach ([resource_path('js/app.ts'), resource_path('js/app.js')] as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return string|null The step left for the developer, or null when none remains.
     */
    private function importStylesheet(string $stylesheet): ?string
    {
        $manualStep = "Add `@import '{$stylesheet}';` after `@import 'tailwindcss';` in your Tailwind entry file.";

        $path = resource_path('css/app.css');

        if (! File::exists($path)) {
            return $manualStep;
        }

        $source = File::get($path);

        if (str_contains($source, $stylesheet)) {
            return null;
        }

        $patched = preg_replace(
            '/^@import\s+([\'"])tailwindcss\1[^;\n]*;[ \t]*$/m',
            '$0'."\n".'@import $1'.$stylesheet.'$1;',
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

    /**
     * Add an import after the last import, or at the top when there is none.
     */
    private function addImport(string $source, string $import): string
    {
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
}
