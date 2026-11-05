<?php

namespace Xelqun\Forms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Xelqun\Forms\Commands\Concerns\EditsHostApp;

/**
 * Connects a plugin that Composer already installed to the app's frontend. What
 * to connect comes from "extra.xelqun" in the plugin's composer.json.
 */
class InstallPluginCommand extends Command
{
    use EditsHostApp;

    private const string COMPOSER_NAME = '/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$/';

    private const string NPM_NAME = '/^(@[a-z0-9-~][a-z0-9-._~]*\/)?[a-z0-9-~][a-z0-9-._~]*$/';

    private const string JS_IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    private const string SOURCE_GLOB = '/^(?!.*\.\.)[A-Za-z0-9_@\/.*{},-]+$/';

    protected $signature = 'xelqun-forms:plugin
        {package : The Composer package, e.g. acme/rating}
        {--without-node : Do not install the npm package}';

    protected $description = 'Add an installed Xelqun Forms plugin to the app\'s frontend';

    public function handle(): int
    {
        $package = (string) $this->argument('package');

        if (preg_match(self::COMPOSER_NAME, $package) !== 1) {
            $this->components->error("[{$package}] is not a Composer package name.");

            return self::FAILURE;
        }

        $path = base_path("vendor/{$package}/composer.json");

        if (! File::exists($path)) {
            $this->components->error("[{$package}] is not installed. Run `composer require {$package}` first.");

            return self::FAILURE;
        }

        $plugin = $this->readPlugin($package, $path);

        if ($plugin === null) {
            return self::FAILURE;
        }

        if (! $this->option('without-node') && ! $this->installNodePackage($plugin['npm'])) {
            return self::FAILURE;
        }

        $manualSteps = array_filter([
            $this->registerPlugin($plugin['npm'], $plugin['plugin']),
            $this->addTailwindSource($plugin['npm'], $plugin['source']),
        ]);

        if ($manualSteps !== []) {
            $this->components->warn('Finish these steps by hand:');
            $this->components->bulletList($manualSteps);

            return self::SUCCESS;
        }

        $this->components->info("[{$package}] is ready. Rebuild your frontend to see it.");

        return self::SUCCESS;
    }

    /**
     * Without "extra.xelqun", acme/rating means ratingPlugin() from @acme/rating.
     *
     * These values end up in a shell command and the app's source code, so
     * anything that doesn't look like what it claims to be is refused.
     *
     * @return array{npm: string, plugin: string, source: string}|null
     */
    private function readPlugin(string $package, string $path): ?array
    {
        $manifest = json_decode(File::get($path), true);
        $extra = is_array($manifest) ? data_get($manifest, 'extra.xelqun', []) : [];
        [$vendor, $name] = explode('/', $package);

        $plugin = [
            'npm' => data_get($extra, 'npm', "@{$vendor}/{$name}"),
            'plugin' => data_get($extra, 'plugin', Str::camel($name).'Plugin'),
            'source' => data_get($extra, 'source', 'resources/js/**/*.vue'),
        ];

        foreach (['npm' => self::NPM_NAME, 'plugin' => self::JS_IDENTIFIER, 'source' => self::SOURCE_GLOB] as $key => $pattern) {
            if (! is_string($plugin[$key]) || preg_match($pattern, $plugin[$key]) !== 1) {
                $this->components->error("The value of extra.xelqun.{$key} in {$package}/composer.json is not valid.");

                return null;
            }
        }

        return $plugin;
    }

    /**
     * @return string|null The step left for the developer, or null when none remains.
     */
    private function registerPlugin(string $npm, string $plugin): ?string
    {
        $manualStep = "Add `{$plugin}()` to the `plugins` of `createXelqunForms()` in your Inertia entry file, importing it from '{$npm}'.";
        $path = $this->findEntryFile();

        if ($path === null) {
            return $manualStep;
        }

        $source = File::get($path);
        $code = $this->blankComments($source);
        $span = $this->findCallArguments($code);

        if ($span === null) {
            return $manualStep;
        }

        [$start, $length] = $span;
        $arguments = substr($code, $start, $length);

        if (preg_match("/\\b{$plugin}\\s*\\(/", $arguments) === 1) {
            return null;
        }

        $patched = $arguments === substr($source, $start, $length)
            ? $this->addToPlugins($arguments, "{$plugin}()")
            : null;

        if ($patched === null) {
            return $manualStep;
        }

        $source = substr_replace($source, $patched, $start, $length);

        if (preg_match("/\\bimport\\s[^'\"]*\\b{$plugin}\\b[^'\"]*\\sfrom\\s/", $code) !== 1) {
            $source = $this->addImport($source, "import { {$plugin} } from '{$npm}';");
        }

        File::put($path, $source);

        return null;
    }

    /**
     * Rewrite the arguments of createXelqunForms(). Only handles shapes it can
     * read with confidence: no options, an object of options, or a plain plugins
     * list. Options in a variable or a spread can't be read here.
     */
    private function addToPlugins(string $arguments, string $call): ?string
    {
        if (trim($arguments) === '') {
            return "{ plugins: [{$call}] }";
        }

        if (str_contains($arguments, '...')
            || preg_match('/^\s*\{(\s*)/', $arguments, $opening) !== 1
            || ! str_ends_with(rtrim($arguments), '}')) {
            return null;
        }

        if (preg_match('/\bplugins\b/', $arguments) === 1) {
            return $this->appendToList($arguments, $call);
        }

        $spacing = str_contains($opening[1], "\n") ? $opening[1] : ' ';

        return substr_replace($arguments, "plugins: [{$call}],{$spacing}", strlen($opening[0]), 0);
    }

    /**
     * Replace comments with spaces, so offsets still match the original source
     * and nothing inside a comment can be mistaken for code.
     */
    private function blankComments(string $source): string
    {
        $code = $source;
        $length = strlen($source);
        $quote = null;

        for ($index = 0; $index < $length; $index++) {
            $character = $source[$index];

            if ($quote !== null) {
                if ($character === '\\') {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if (in_array($character, ['"', "'", '`'], true)) {
                $quote = $character;

                continue;
            }

            $opener = substr($source, $index, 2);

            if ($opener !== '//' && $opener !== '/*') {
                continue;
            }

            $end = $opener === '//' ? strpos($source, "\n", $index) : strpos($source, '*/', $index + 2);
            $end = $end === false ? $length : $end + ($opener === '//' ? 0 : 2);
            $code = substr_replace($code, str_repeat(' ', $end - $index), $index, $end - $index);
            $index = $end - 1;
        }

        return $code;
    }

    /**
     * Where the arguments of createXelqunForms( are, skipping over strings.
     *
     * @return array{int, int}|null Offset and length.
     */
    private function findCallArguments(string $code): ?array
    {
        if (preg_match('/\bcreateXelqunForms\(/', $code, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $start = $match[0][1] + strlen($match[0][0]);
        $depth = 1;
        $quote = null;

        for ($index = $start; $index < strlen($code); $index++) {
            $character = $code[$index];

            if ($quote !== null) {
                if ($character === '\\') {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if (in_array($character, ['"', "'", '`'], true)) {
                $quote = $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')' && --$depth === 0) {
                return [$start, $index - $start];
            }
        }

        return null;
    }

    private function appendToList(string $arguments, string $call): ?string
    {
        if (preg_match('/\bplugins\s*:\s*\[([^\[\]]*)\]/', $arguments, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        [$items, $offset] = $match[1];
        $existing = rtrim($items);

        if ($existing === '') {
            return substr_replace($arguments, $call, $offset, strlen($items));
        }

        $list = str_contains($items, "\n")
            ? rtrim($existing, ',').",\n".$this->lastLineIndent($existing).$call.','.substr($items, strlen($existing))
            : rtrim($existing, ', ').", {$call}";

        return substr_replace($arguments, $list, $offset, strlen($items));
    }

    private function lastLineIndent(string $text): string
    {
        preg_match('/(?:^|\n)([ \t]*)[^\n]*$/', $text, $match);

        return $match[1] ?? '';
    }

    /**
     * @return string|null The step left for the developer, or null when none remains.
     */
    private function addTailwindSource(string $npm, string $glob): ?string
    {
        $line = "@source '../../node_modules/{$npm}/{$glob}';";
        $manualStep = "Add `{$line}` to your Tailwind entry file, so its classes are generated.";
        $path = resource_path('css/app.css');

        if (! File::exists($path)) {
            return $manualStep;
        }

        $source = File::get($path);

        if (str_contains($source, $line)) {
            return null;
        }

        foreach (['/^@source\s[^\n]*$/m', '/^@import\s+[\'"]@xelqun\/forms\/style\.css[\'"][^\n]*$/m', '/^@import\s+[\'"]tailwindcss[\'"][^\n]*$/m'] as $anchor) {
            if (preg_match_all($anchor, $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
                [$text, $offset] = end($matches[0]);

                File::put($path, substr_replace($source, "\n{$line}", $offset + strlen($text), 0));

                return null;
            }
        }

        return $manualStep;
    }
}
