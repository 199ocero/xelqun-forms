# Xelqun Forms

PHP-defined, schema-driven forms for **Laravel + Inertia + Vue**. You describe a form in PHP, the package turns it into a versioned JSON schema, and generic Vue components render it. No Vue to write for normal forms.

> **Status: pre-alpha.** The API will change. Built and tested against Laravel 13, Inertia 3, Vue 3.5 and Tailwind 4.

```php
class OrderForm extends Form
{
    public function schema(): array
    {
        return [
            TextInput::make('customer')->required(),
            Select::make('country_id')->relationship('country', 'name')
                ->afterStateUpdated(fn (Set $set) => $set('state_id', null)),
            Select::make('state_id')
                ->options(fn (Get $get) => State::where('country_id', $get('country_id'))->pluck('name', 'id')),
            Section::make('Business')->visibleWhen(When::eq('type', 'business'))->schema([
                TextInput::make('company')->requiredWhen(When::eq('type', 'business')),
            ]),
            Repeater::make('items')->relationship()->schema([
                TextInput::make('qty')->numeric(),
                TextInput::make('price')->numeric(),
                TextInput::make('subtotal')->computed('qty * price')->disabled(),
            ]),
            TextInput::make('total')->computed('sum(items.*.subtotal)')->disabled(),
        ];
    }
}
```

## How it works

- **Three tiers of rules.** Declarative conditions (`visibleWhen`, `requiredWhen`, `disabledWhen`) and calculations (`computed('qty * price')`) run in the browser with no server call. The server re-checks every condition, drops hidden values and recomputes every total on submit. Conditional text does the same for wording: `->placeholder(When::eq('is_gift', true)->then('Add a gift message…')->else('Anything we should know?'))` also works on `label()` and `hint()`, and costs no request. Server closures (`options(fn (Get $get) => ...)`, `afterStateUpdated`) run only on the server, and any field they read through `Get` sends an update when it changes.
- **Automatic dependency tracking.** Closures read state through `Get`, which records what they read. The browser calls the server only when one of those paths changes, and the response is a small patch that is merged in place, so focus and cursor position are kept.
- **Secure by default.** Forms are rebuilt on the server from an encrypted token (never a class name from the client). Every endpoint re-runs `authorize()` and validation.
- **Stable row keys.** Repeater rows are keyed by UUIDs (or `record-{id}`), so Laravel validation errors map straight onto nested fields.
- **Uploads stay out of the form state.** Files upload as soon as they are picked, and the state only holds a short token until the form is saved.

## Installation

```bash
composer require xelqun/forms && php artisan xelqun-forms:install
```

The installer adds the matching version of the `@xelqun/forms` npm package with your package manager (npm, pnpm, Yarn or Bun, detected from the lockfile). It then adds the plugin to `resources/js/app.ts` and the stylesheet to `resources/css/app.css`. It's safe to run again, so run it after `composer update` to bring the npm package up to the same version.

Use `--config` to publish the config file, or `--without-node` to skip the npm step. If the installer can't edit a file safely, it leaves the file alone and prints the step to do by hand:

```ts
// resources/js/app.ts
import { createXelqunForms } from '@xelqun/forms';

createInertiaApp({
    withApp: (app) => app.use(createXelqunForms()),
});
```

```css
/* resources/css/app.css */
@import 'tailwindcss';
@import '@xelqun/forms/style.css';
```

Then render a form:

```php
// controller
return Inertia::render('Orders/Edit', ['form' => OrderForm::make($order)->toSchema()]);

public function update(Request $request, Order $order)
{
    OrderForm::make($order)->save($request);

    return back();
}
```

```vue
<script setup lang="ts">
import { XqForm, type FormSchema } from '@xelqun/forms';

defineProps<{ form: FormSchema; orderId: number }>();
</script>

<template>
    <XqForm :form="form" :action="`/orders/${orderId}`" method="put" />
</template>
```

Section titles start at `h2`. If the form sits lower in your page's heading order, set it with `<XqForm :heading-level="3" />`.

Components use their own `--xq-*` design tokens and `Xq*` names, so they work next to an app's own shadcn-vue components. Dark mode follows a `.dark` class or `[data-xq-theme=dark]`.

## Security

- **Schedule the cleanup.** Temporary uploads stay on disk until `php artisan xelqun-forms:prune-uploads` removes them, so run it from your scheduler.
- **Authorize in `authorize()`.** `validate()`, `save()` and every internal endpoint call it, and it allows everything by default.
- **Use `hidden()` to protect a field, not `visibleWhen()`.** `visibleWhen()` runs against state the browser controls. `hidden()` also blocks the field's search, upload and hooks.
- **`Hidden` fields are not secret or read-only.** Their value comes back from the browser, so use `disabled()` for values users must not change.
- **A disabled field submits what it was filled with**, even with `dehydrated()`, because the browser can't be trusted to send it back unchanged. To save a value the server derives, set it in `mutateBeforeSave()`.
- **Limit uploads.** Without `acceptedFileTypes()` or `image()`, a field accepts any file except types a web server may run or a browser may treat as a page.

## Development

```bash
composer install && vendor/bin/pest          # PHP tests
npm install && npx vp test                   # Vitest
vendor/bin/pint && vendor/bin/phpstan analyse
npx vp check && npx vue-tsc --noEmit
```

The conformance fixtures in `tests/Fixtures/conformance` run in both Pest and Vitest, so the PHP and browser evaluators always agree. See [`docs/forms-plan.md`](docs/forms-plan.md) for the design.

## License

MIT
