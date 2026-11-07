<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\ServiceProvider;
use Xelqun\Forms\FormsServiceProvider;
use Xelqun\Forms\Schema\SchemaVersion;

beforeEach(function (): void {
    $this->appPath = sys_get_temp_dir().'/xelqun-install-'.uniqid();

    File::ensureDirectoryExists("{$this->appPath}/resources/js");
    File::ensureDirectoryExists("{$this->appPath}/resources/css");
    File::ensureDirectoryExists("{$this->appPath}/config");
    File::put("{$this->appPath}/package.json", '{}');

    $this->packageSpec = '@xelqun/forms@'.SchemaVersion::PACKAGE;

    $this->app->setBasePath($this->appPath);
});

afterEach(function (): void {
    File::deleteDirectory($this->appPath);
});

it('keeps the npm version in step with the PHP package', function (): void {
    $manifest = json_decode(File::get(__DIR__.'/../../package.json'), true);

    expect($manifest['version'])->toBe(SchemaVersion::PACKAGE);
});

it('installs the npm package that matches the PHP package', function (?string $lockfile, string $command): void {
    Process::fake();

    if ($lockfile !== null) {
        File::put("{$this->appPath}/{$lockfile}", '');
    }

    $this->artisan('xelqun-forms:install')->assertSuccessful();

    Process::assertRan(fn ($process): bool => $process->command === "{$command} {$this->packageSpec}"
        && $process->path === $this->appPath);
})->with([
    'npm by default' => [null, 'npm install'],
    'npm lockfile' => ['package-lock.json', 'npm install'],
    'pnpm' => ['pnpm-lock.yaml', 'pnpm add'],
    'yarn' => ['yarn.lock', 'yarn add'],
    'bun' => ['bun.lock', 'bun add'],
    'bun binary lockfile' => ['bun.lockb', 'bun add'],
]);

it('fails without touching the app when there is no package.json', function (): void {
    Process::fake();
    File::delete("{$this->appPath}/package.json");
    File::put("{$this->appPath}/resources/js/app.ts", $source = "createInertiaApp({\n    title: 'x',\n});\n");

    $this->artisan('xelqun-forms:install')->assertFailed();

    Process::assertNothingRan();
    expect(File::get("{$this->appPath}/resources/js/app.ts"))->toBe($source);
});

it('leaves the app files alone and prints the command when the npm install fails', function (): void {
    Process::fake(['*' => Process::result(exitCode: 1)]);
    File::put("{$this->appPath}/resources/js/app.ts", $script = "createInertiaApp({\n    title: 'x',\n});\n");
    File::put("{$this->appPath}/resources/css/app.css", $styles = "@import 'tailwindcss';\n");

    $this->artisan('xelqun-forms:install')
        ->expectsOutputToContain("npm install {$this->packageSpec}")
        ->assertFailed();

    expect(File::get("{$this->appPath}/resources/js/app.ts"))->toBe($script)
        ->and(File::get("{$this->appPath}/resources/css/app.css"))->toBe($styles);
});

it('skips the npm install with --without-node', function (): void {
    Process::fake();
    File::put("{$this->appPath}/resources/css/app.css", "@import 'tailwindcss';\n");

    $this->artisan('xelqun-forms:install --without-node')->assertSuccessful();

    Process::assertNothingRan();
    expect(File::get("{$this->appPath}/resources/css/app.css"))->toContain('@xelqun/forms/style.css');
});

it('keeps the packages out of Vite pre-bundling', function (): void {
    Process::fake();
    File::put("{$this->appPath}/vite.config.ts", <<<'TS'
    import { defineConfig } from 'vite';

    export default defineConfig({
        plugins: [],
    });
    TS);

    $this->artisan('xelqun-forms:install')->assertSuccessful();
    $this->artisan('xelqun-forms:install')->assertSuccessful();

    expect(File::get("{$this->appPath}/vite.config.ts"))->toBe(<<<'TS'
    import { defineConfig } from 'vite';

    export default defineConfig({
        // Pre-bundling these would split them from their .vue files.
        optimizeDeps: {
            exclude: ['@xelqun/forms', '@xelqun/tables', '@xelqun/panels'],
        },
        plugins: [],
    });
    TS);
});

it('prints the Vite step when it cannot edit the config with confidence', function (?string $config): void {
    Process::fake();

    if ($config !== null) {
        File::put("{$this->appPath}/vite.config.js", $config);
    }

    $this->artisan('xelqun-forms:install')
        ->expectsOutputToContain('optimizeDeps')
        ->assertSuccessful();

    if ($config !== null) {
        expect(File::get("{$this->appPath}/vite.config.js"))->toBe($config);
    }
})->with([
    'no config' => [null],
    'a function config' => ["export default defineConfig(({ mode }) => ({\n    plugins: [],\n}));\n"],
    'an optimizeDeps of its own' => ["export default defineConfig({\n    optimizeDeps: { include: ['x'] },\n});\n"],
]);

it('registers the plugin in the Inertia entry file', function (string $source, string $expected): void {
    Process::fake();
    File::put("{$this->appPath}/resources/js/app.ts", $source);
    File::put("{$this->appPath}/resources/css/app.css", "@import 'tailwindcss';\n");

    $this->artisan('xelqun-forms:install')->assertSuccessful();

    expect(File::get("{$this->appPath}/resources/js/app.ts"))->toBe($expected);
})->with([
    'withApp arrow function' => [
        <<<'TS'
        import { createInertiaApp } from '@inertiajs/vue3';
        import AppLayout from '@/layouts/AppLayout.vue';

        void createInertiaApp({
            withApp: (app) => {
                app.directive('focus', {});
            },
        });
        TS,
        <<<'TS'
        import { createInertiaApp } from '@inertiajs/vue3';
        import AppLayout from '@/layouts/AppLayout.vue';
        import { createXelqunForms } from '@xelqun/forms';

        void createInertiaApp({
            withApp: (app) => {
                app.use(createXelqunForms());
                app.directive('focus', {});
            },
        });
        TS,
    ],
    'withApp with a second argument' => [
        <<<'TS'
        import { createInertiaApp } from '@inertiajs/vue3';

        createInertiaApp({
            withApp: (vueApp, { ssr }) => {
                vueApp.provide('ssr', ssr);
            },
        });
        TS,
        <<<'TS'
        import { createInertiaApp } from '@inertiajs/vue3';
        import { createXelqunForms } from '@xelqun/forms';

        createInertiaApp({
            withApp: (vueApp, { ssr }) => {
                vueApp.use(createXelqunForms());
                vueApp.provide('ssr', ssr);
            },
        });
        TS,
    ],
    'withApp method' => [
        <<<'TS'
        import { createInertiaApp } from '@inertiajs/vue3';

        createInertiaApp({
            withApp(app) {
                app.provide('a', 1);
            },
        });
        TS,
        <<<'TS'
        import { createInertiaApp } from '@inertiajs/vue3';
        import { createXelqunForms } from '@xelqun/forms';

        createInertiaApp({
            withApp(app) {
                app.use(createXelqunForms());
                app.provide('a', 1);
            },
        });
        TS,
    ],
    'setup with a method chain' => [
        <<<'TS'
        import { createInertiaApp } from '@inertiajs/vue3';
        import { createApp, h } from 'vue';

        createInertiaApp({
            setup({ el, App, props, plugin }) {
                createApp({ render: () => h(App, props) })
                    .use(plugin)
                    .mount(el);
            },
        });
        TS,
        <<<'TS'
        import { createInertiaApp } from '@inertiajs/vue3';
        import { createApp, h } from 'vue';
        import { createXelqunForms } from '@xelqun/forms';

        createInertiaApp({
            setup({ el, App, props, plugin }) {
                createApp({ render: () => h(App, props) })
                    .use(plugin)
                    .use(createXelqunForms())
                    .mount(el);
            },
        });
        TS,
    ],
    'setup on a single line' => [
        <<<'TS'
        import { createInertiaApp } from '@inertiajs/vue3';

        createInertiaApp({
            setup({ el, App, plugin }) {
                createApp(App).use(plugin).mount(el);
            },
        });
        TS,
        <<<'TS'
        import { createInertiaApp } from '@inertiajs/vue3';
        import { createXelqunForms } from '@xelqun/forms';

        createInertiaApp({
            setup({ el, App, plugin }) {
                createApp(App).use(plugin).use(createXelqunForms()).mount(el);
            },
        });
        TS,
    ],
    'no withApp or setup yet' => [
        <<<'TS'
        import { createInertiaApp } from '@inertiajs/vue3';

        createInertiaApp({
            title: (title) => title,
        });
        TS,
        <<<'TS'
        import { createInertiaApp } from '@inertiajs/vue3';
        import { createXelqunForms } from '@xelqun/forms';

        createInertiaApp({
            withApp: (app) => {
                app.use(createXelqunForms());
            },
            title: (title) => title,
        });
        TS,
    ],
    'imports over several lines without semicolons' => [
        <<<'TS'
        import {
            createInertiaApp,
        } from '@inertiajs/vue3'
        import '../css/app.css'

        createInertiaApp({
            title: (title) => title,
        })
        TS,
        <<<'TS'
        import {
            createInertiaApp,
        } from '@inertiajs/vue3'
        import '../css/app.css'
        import { createXelqunForms } from '@xelqun/forms';

        createInertiaApp({
            withApp: (app) => {
                app.use(createXelqunForms());
            },
            title: (title) => title,
        })
        TS,
    ],
]);

it('uses app.js when the app has no app.ts', function (): void {
    Process::fake();
    File::put("{$this->appPath}/resources/js/app.js", "createInertiaApp({\n    title: 'x',\n});\n");
    File::put("{$this->appPath}/resources/css/app.css", "@import 'tailwindcss';\n");

    $this->artisan('xelqun-forms:install')->assertSuccessful();

    expect(File::get("{$this->appPath}/resources/js/app.js"))
        ->toStartWith("import { createXelqunForms } from '@xelqun/forms';")
        ->toContain('app.use(createXelqunForms());');
});

it('prints the step and leaves the entry file alone when it cannot read its shape', function (string $source): void {
    Process::fake();
    File::put("{$this->appPath}/resources/js/app.ts", $source);
    File::put("{$this->appPath}/resources/css/app.css", "@import 'tailwindcss';\n");

    $this->artisan('xelqun-forms:install')
        ->expectsOutputToContain('Finish these steps by hand')
        ->expectsOutputToContain('app.use(createXelqunForms())')
        ->assertSuccessful();

    expect(File::get("{$this->appPath}/resources/js/app.ts"))->toBe($source);
})->with([
    'expression body' => ["createInertiaApp({\n    withApp: (app) => app.use(other),\n});\n"],
    'named function' => ["createInertiaApp({\n    withApp: configureApp,\n});\n"],
    'function expression' => ["createInertiaApp({\n    withApp: function (app) {\n        app.use(other);\n    },\n});\n"],
    'empty withApp' => ["createInertiaApp({\n    withApp: (app) => {},\n});\n"],
    'setup without the Inertia plugin call' => ["createInertiaApp({\n    setup(options) {\n        boot(options);\n    },\n});\n"],
    'no Inertia call' => ["console.log('hello');\n"],
]);

it('prints the step when there is no entry file', function (): void {
    Process::fake();
    File::put("{$this->appPath}/resources/css/app.css", "@import 'tailwindcss';\n");

    $this->artisan('xelqun-forms:install')
        ->expectsOutputToContain('app.use(createXelqunForms())')
        ->assertSuccessful();
});

it('imports the stylesheet right after tailwindcss', function (string $source, string $expected): void {
    Process::fake();
    File::put("{$this->appPath}/resources/css/app.css", $source);

    $this->artisan('xelqun-forms:install')->assertSuccessful();

    expect(File::get("{$this->appPath}/resources/css/app.css"))->toBe($expected);
})->with([
    'single quotes' => [
        "@import 'tailwindcss';\n\n@import 'tw-animate-css';\n",
        "@import 'tailwindcss';\n@import '@xelqun/forms/style.css';\n\n@import 'tw-animate-css';\n",
    ],
    'double quotes' => [
        "@import \"tailwindcss\";\n",
        "@import \"tailwindcss\";\n@import \"@xelqun/forms/style.css\";\n",
    ],
    'import with options' => [
        "@import 'tailwindcss' source(none);\n",
        "@import 'tailwindcss' source(none);\n@import '@xelqun/forms/style.css';\n",
    ],
]);

it('prints the step when the stylesheet cannot be added', function (?string $source): void {
    Process::fake();
    File::put("{$this->appPath}/resources/js/app.ts", "createInertiaApp({\n    title: 'x',\n});\n");

    if ($source !== null) {
        File::put("{$this->appPath}/resources/css/app.css", $source);
    }

    $this->artisan('xelqun-forms:install')
        ->expectsOutputToContain('@xelqun/forms/style.css')
        ->assertSuccessful();

    if ($source !== null) {
        expect(File::get("{$this->appPath}/resources/css/app.css"))->toBe($source);
    }
})->with([
    'no Tailwind 4 import' => ["@tailwind base;\n"],
    'no stylesheet' => [null],
]);

it('changes nothing when run a second time', function (): void {
    Process::fake();
    File::put("{$this->appPath}/resources/js/app.ts", "createInertiaApp({\n    title: 'x',\n});\n");
    File::put("{$this->appPath}/resources/css/app.css", "@import 'tailwindcss';\n");

    $this->artisan('xelqun-forms:install')->assertSuccessful();

    $script = File::get("{$this->appPath}/resources/js/app.ts");
    $styles = File::get("{$this->appPath}/resources/css/app.css");

    $this->artisan('xelqun-forms:install')->assertSuccessful();

    expect(File::get("{$this->appPath}/resources/js/app.ts"))->toBe($script)
        ->and(File::get("{$this->appPath}/resources/css/app.css"))->toBe($styles);
});

it('publishes the config only when asked', function (string $command, bool $published): void {
    Process::fake();
    File::put("{$this->appPath}/resources/js/app.ts", "createInertiaApp({\n    title: 'x',\n});\n");
    File::put("{$this->appPath}/resources/css/app.css", "@import 'tailwindcss';\n");

    $target = array_values(ServiceProvider::pathsToPublish(FormsServiceProvider::class, 'xelqun-forms-config'))[0];

    $this->artisan($command)->assertSuccessful();

    expect(File::exists($target))->toBe($published);

    File::delete($target);
})->with([
    'with --config' => ['xelqun-forms:install --config', true],
    'without it' => ['xelqun-forms:install', false],
]);
