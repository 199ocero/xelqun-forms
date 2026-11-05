<p align="center">
    <img src="https://raw.githubusercontent.com/199ocero/xelqun-forms/main/.github/art/banner.png" alt="Xelqun Forms: PHP-defined forms for Laravel, Inertia and Vue" width="100%">
</p>

<p align="center">
    <a href="https://packagist.org/packages/xelqun/forms"><img src="https://img.shields.io/packagist/v/xelqun/forms?label=packagist&color=414fd2" alt="Packagist version"></a>
    <a href="https://www.npmjs.com/package/@xelqun/forms"><img src="https://img.shields.io/npm/v/@xelqun/forms?label=npm&color=414fd2" alt="npm version"></a>
    <a href="https://packagist.org/packages/xelqun/forms/stats"><img src="https://img.shields.io/packagist/dt/xelqun/forms?color=414fd2" alt="Total downloads"></a>
</p>

Describe a form in PHP and generic Vue components render it. Normal forms need no Vue at all.

> **Pre-alpha:** the API will change. Requires Laravel 13, Inertia 3, Vue 3.5 and Tailwind 4.

## Example

```php
class OrderForm extends Form
{
    public function schema(): array
    {
        return [
            TextInput::make('customer')->required(),
            Select::make('country_id')->relationship('country', 'name'),
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

## Features

- **Write PHP, not Vue.** Normal forms need no frontend code.
- **Instant browser rules.** Conditions and calculations run without a server call, and the server re-checks them on submit.
- **Small server updates.** Dependent fields update through small patches that keep focus and cursor position.
- **Secure by default.** Forms rebuild from an encrypted token, and every endpoint re-runs `authorize()` and validation.
- **Repeaters and uploads included.** Nested rows map to validation errors, and files upload as soon as they're picked.

## Installation

```bash
composer require xelqun/forms && php artisan xelqun-forms:install
```

The installer adds the matching `@xelqun/forms` npm package and sets up `resources/js/app.ts` and `resources/css/app.css`.

## Usage

```php
class OrderController
{
    // Show the form: GET /orders/{order}/edit
    public function edit(Order $order)
    {
        return Inertia::render('Orders/Edit', [
            'form' => OrderForm::make($order)->toSchema(),
            'orderId' => $order->id,
        ]);
    }

    // Save the form: PUT /orders/{order}
    public function update(Request $request, Order $order)
    {
        OrderForm::make($order)->save($request);

        return back();
    }
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

## Plugins

Other packages can add fields, with the same search, uploads, rows and saving as built-in ones. Install one with `php artisan xelqun-forms:plugin acme/rating`, or build one with the [plugin guide](docs/plugins.md).

## Before going to production

- **Add your rules to `authorize()`.** It allows everything by default.
- **Schedule `php artisan xelqun-forms:prune-uploads`.** Temporary uploads stay on disk until it runs.

## License

MIT
