# Building a Xelqun Forms plugin

A plugin adds fields to Xelqun Forms from its own repository. Built-in fields use the same contracts, so a plugin field can do what a built-in field does: search the server, take uploads, hold rows and save its own data.

A working plugin lives in [`tests/Fixtures/ExamplePlugin`](../tests/Fixtures/ExamplePlugin). The package's tests run against it on every change, so it stays correct. Read it alongside this guide.

## How a plugin is laid out

A plugin is one repository with a `composer.json` and a `package.json`:

```
acme-rating/
├── composer.json
├── package.json
├── src/
│   ├── RatingServiceProvider.php
│   └── Rating.php
└── resources/
    ├── js/
    │   ├── index.ts
    │   └── components/RatingField.vue
    └── lang/en/rating.php
```

`composer.json` registers the service provider and tells the install command what to connect:

```json
{
    "name": "acme/rating",
    "autoload": { "psr-4": { "Acme\\Rating\\": "src/" } },
    "extra": {
        "laravel": { "providers": ["Acme\\Rating\\RatingServiceProvider"] },
        "xelqun": { "npm": "@acme/rating", "plugin": "ratingPlugin" }
    }
}
```

Without `extra.xelqun`, `acme/rating` means `ratingPlugin()` from `@acme/rating`, with Tailwind scanning `resources/js/**/*.vue`. Set `source` to change that glob.

`package.json` ships your source, because the host's Vite compiles it. Declare the shared packages as **peer dependencies**. A second copy of `vue` or `reka-ui` silently breaks reactivity and popovers:

```json
{
    "name": "@acme/rating",
    "type": "module",
    "files": ["resources/js"],
    "exports": { ".": "./resources/js/index.ts" },
    "peerDependencies": {
        "@xelqun/forms": "^0.1.0",
        "reka-ui": "^2.11.0",
        "vue": "^3.5.0"
    }
}
```

No plugin class is needed on the PHP side. Your service provider is enough.

## A field

`php artisan make:xelqun-field Rating --type=acme/rating` creates both halves. Add `--search` or `--uploads` to start from a field that does those. In a plugin repository, move the files into your `src/` and `resources/js/`.

A field is a PHP class:

```php
class Rating extends Field
{
    public function getType(): string
    {
        return 'acme/rating';
    }

    protected function getTypeRules(): array
    {
        return ['integer', 'between:1,5'];
    }

    protected function getProps(): array
    {
        return ['max' => 5];
    }
}
```

And a Vue component that receives the schema node. `useField` binds it to the form, and `XqFieldWrapper` gives it the label, hint and error:

```vue
<script setup lang="ts">
import { useField, XqFieldWrapper } from '@xelqun/forms';
import type { SchemaNode } from '@xelqun/forms';

const props = defineProps<{ node: SchemaNode }>();
const { value, error, required, disabled, id } = useField<number | null>(props.node);
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required">
        <input :id="id" v-model.number="value" type="number" :disabled="disabled" />
    </XqFieldWrapper>
</template>
```

Then register the renderer:

```ts
import type { XelqunPlugin } from '@xelqun/forms';

export function ratingPlugin(): XelqunPlugin {
    return {
        id: 'acme/rating',
        components: { 'acme/rating': () => import('./components/RatingField.vue') },
    };
}
```

### Type names

Plugin types need a vendor prefix: `acme/rating`. Names without one are reserved for built-in fields.

If a plugin registers an unprefixed name, or two plugins register one type, `createXelqunForms()` throws in development. In production it logs the error and keeps the type registered first, so the page still works. To replace a built-in field on purpose, say so:

```ts
{ id: 'acme/fancy-select', components: { select: FancySelect }, replaces: ['select'] }
```

`replaces` only works on built-in fields, and only one plugin can replace each. A plugin can't take over another plugin's type. An app's own `components` option always wins, with no flag needed.

A plugin can also have a `setup({ app })` function, which runs once after every plugin has registered. Use it to add to the Vue app, for example a directive. It can't change which renderer a type uses.

## What a field can do

Implement a contract from `Xelqun\Forms\Contracts` and the matching endpoint starts working for your field.

| Contract | What it gives you | Browser side |
|---|---|---|
| `HasSearchResults` | The search endpoint | `store.search(path, term, signal)` |
| `AcceptsUploads` | The upload endpoint and storage on save | `store.upload(path, file, onProgress, signal)` |
| `EmbedsUploads` | The same for uploads kept inside a field's text. See `HasFileAttachments` | `store.upload(path, file, onProgress, signal)` |
| `ContainsRows` | Rows with their own components | `store.addRow`, `removeRow`, `moveRow` |
| `PersistsItself` | Saving somewhere other than a column | None |
| `PersistsBeforeSave` | Also doing something before the record is saved, such as setting a foreign key | None |
| `NeedsServer` | A clear error on forms the server can't rebuild | None |

`store` comes from `useField()`.

### Search

```php
class Reviewer extends Field implements HasSearchResults
{
    use HasOptions;

    public function isSearchable(): bool { return true; }

    public function isAsync(): bool { return true; }

    public function getSearchResults(string $search): array
    {
        return $this->toOptionList(/* value => label */);
    }
}
```

**Validate the submitted value too.** Search only suggests values. The browser can post anything, so `getTypeRules()` has to check it. See `Reviewer.php` in the example. In a closure rule, fail with `$this->getFailureMessage('in')`, so `validationMessages()` still works on your field.

### Uploads

Use the `HandlesUploads` trait rather than writing `AcceptsUploads` yourself:

```php
class Receipt extends Field implements AcceptsUploads
{
    use HandlesUploads;

    public function getType(): string
    {
        return 'acme/receipt';
    }
}
```

The trait adds `disk()`, `directory()`, `maxSize()`, `acceptedFileTypes()` and `image()`, along with the rules for the state. Those rules matter: without them, a posted value could name any path, and it would be saved as the field's file. To add your own rules or props, spread `getUploadStateRules()` and `getUploadProps()` instead of replacing them.

The field's state holds a token or a stored path, or a list of them with `multiple()`. On save each token becomes its stored path, and a failed save keeps the uploads so the user can submit again. If your Vue field adds files to a queue of its own, pass that work to `store.trackUpload()`, so a submit waits for files that haven't started yet.

### Saving somewhere else

By default a field's value goes in the column named after it. `PersistsItself` lets a field write it elsewhere, and overriding `fillState()` reads it back:

```php
public function shouldPersistItself(): bool
{
    return $this->relationship !== null;
}

public function persist(Model $record, mixed $state): void
{
    $record->{$this->relationship}()->updateOrCreate([], ['stars' => $state]);
}
```

`persist()` runs inside the transaction, after the record is saved. Throwing rolls the whole save back. It only runs for a submitted value, so a hidden field is skipped. **Never treat a missing value as "clear this".** `$state` can still be null if the field was submitted empty.

To set something on the record itself, implement `PersistsBeforeSave` as well. `persistBeforeSave()` runs before the record is saved, so a new record has no key yet, and anything you set on it is saved with it:

```php
public function persistBeforeSave(Model $record, mixed $state): void
{
    $record->setAttribute('reviewer_id', $state);
}
```

Inside a relationship repeater, `$record` is the row's model.

### Rows

Extend `Repeater`. `ContainsRows` describes what the server needs, but the browser also needs the props `Repeater` sends: `template`, `rowPlaceholder`, `rows` and `newRow`.

## Changing existing fields

Call these from a service provider's `boot()`.

`configureUsing()` runs on every new instance of a class and its subclasses, after `setUp()` and before the caller's own settings, so the caller has the last word:

```php
TextInput::configureUsing(fn (TextInput $input) => $input->maxLength(255));
```

Macros add methods:

```php
Field::macro('helpful', fn (): static => $this->hint('We will never share this.'));
```

Laravel keeps macros in one list for the whole class tree, so `TextInput::macro()` adds the method to every component, not only `TextInput`.

## Translations

Load your translations in the service provider, and use them for messages and props:

```php
$this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'acme-rating');

$fail(__('acme-rating::rating.range', ['max' => $this->max]));
```

The package's own server text is in `xelqun-forms::messages`. Run `php artisan vendor:publish --tag=xelqun-forms-lang` to change it. Its browser text is in `xelqun-forms::client`.

`useTranslator()` gives a Vue component the same `t()` the package uses, which fills `:name` placeholders. It only knows the package's lines, so send your own text through your field's props, translated in PHP.

## Installing a plugin

```bash
composer require acme/rating
php artisan xelqun-forms:plugin acme/rating
```

The command installs the npm package, adds the plugin to `createXelqunForms()` in `app.ts`, and adds a Tailwind `@source` to `app.css`. If it can't read a file with confidence, such as options in a variable or a spread, or a comment inside the call, it leaves that file alone and prints the step. Use `--without-node` if the npm package is already installed.

## Testing

Add `Xelqun\Forms\Testing\InteractsWithForms` to your test case, or `uses(InteractsWithForms::class)` in Pest. It drives the real endpoints:

```php
$this->searchForm(ReviewForm::make(), 'data.reviewer', 'ada')
    ->assertJsonPath('options.0.value', 'ada');

$token = $this->uploadToForm(ReviewForm::make(), 'data.receipt', $file)->json('token');

$this->updateForm($form, ['data.country'], ['country' => 'PH'])->assertOk();

$this->formNode($form, 'data.items.row-0.stars');
$this->assertNamespacedField($form, 'data.stars');
```

For the browser side, `@xelqun/forms/testing` builds a schema without a server:

```ts
import { fieldNode, formSchema } from '@xelqun/forms/testing';

mount(XqForm, {
    props: { form: formSchema([fieldNode('data.stars', 'acme/rating')], { stars: null }) },
    global: { plugins: [createXelqunForms({ plugins: [ratingPlugin()] })] },
});
```

## Security

- **Plugin code runs with full access to the app.** The contracts control how requests reach a field. They can't make a malicious package safe.
- **The endpoints call `acceptsInput()` and the form's `authorize()` before your code**, so hidden, disabled and read-only fields are never searched or uploaded to.
- **Everything else is yours to check.** If you add your own route, authorize it yourself. Treat every posted value as untrusted, and never rely on the browser hiding something.

## What you can rely on

Classes and methods tagged `@api` are the public API. `@internal` marks exceptions inside an `@api` class, and anything untagged may change without notice. Before 1.0, changes to `@api` items are listed in the release notes.

On the browser side, the public API is what `@xelqun/forms` and `@xelqun/forms/testing` export.

## Current limits

- **A repeater with no relationship has no row model**, so `persist()` isn't called in its rows and the value stays in the row's data.
- **Icons come from a fixed set.** Add your own with `icons` on your plugin, as `{ name: component }`.
- **Debounce depends on the type.** A text-like field that another field depends on sends every change, because the browser only debounces built-in text types. Call `live(debounce: 400)` on your field to set it.
- **Actions have no contract of their own.** An `Action` goes in a field slot with `suffixAction()`, and an action your Vue component runs itself can use `sendsResult()` and `store.runAction(node)`. See `MarkdownEditor`.
