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
            $this->components->error('No package.json found. Xelqun Forms needs an app that builds its frontend with Vite.');

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
