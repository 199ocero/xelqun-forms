<?php

use Illuminate\Support\Facades\DB;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Tests\Fixtures\Order;
use Xelqun\Forms\Tests\Fixtures\OrderForm;
use Xelqun\Forms\Tests\Fixtures\SimpleOrderForm;

function orderInput(array $items): array
{
    return ['data' => [
        'customer' => 'Ada',
        'type' => 'individual',
        'country_id' => null,
        'region_id' => null,
        'items' => $items,
    ]];
}

it('creates the record and its has-many rows in order', function () {
    $order = OrderForm::make()->save(orderInput([
        'u1' => ['product' => 'Desk', 'qty' => 2, 'price' => 10],
        'u2' => ['product' => 'Lamp', 'qty' => 1, 'price' => 5],
    ]));

    expect($order->exists)->toBeTrue()
        ->and((float) $order->total)->toBe(25.0)
        ->and($order->items()->orderBy('sort')->pluck('product')->all())->toBe(['Desk', 'Lamp'])
        ->and((float) $order->items()->where('product', 'Desk')->value('subtotal'))->toBe(20.0);
});

it('fills existing rows with record keys and syncs updates and deletes', function () {
    $order = OrderForm::make()->save(orderInput([
        'u1' => ['product' => 'Desk', 'qty' => 2, 'price' => 10],
        'u2' => ['product' => 'Lamp', 'qty' => 1, 'price' => 5],
    ]));

    $form = OrderForm::make($order->fresh());
    $rows = $form->getData()['items'];
    $deskKey = Repeater::RECORD_KEY_PREFIX.$order->items()->where('product', 'Desk')->value('id');

    expect(array_keys($rows))->toContain($deskKey)
        ->and($rows[$deskKey]['qty'])->toEqual(2);

    $form->save(orderInput([
        $deskKey => ['product' => 'Standing desk', 'qty' => 1, 'price' => 30],
        'u3' => ['product' => 'Chair', 'qty' => 2, 'price' => 15],
    ]));

    expect($order->items()->orderBy('sort')->pluck('product')->all())->toBe(['Standing desk', 'Chair'])
        ->and((float) $order->fresh()->total)->toBe(60.0);
});

it('never updates rows that belong to another record', function () {
    $other = Order::create(['customer' => 'Other']);
    $foreignItem = $other->items()->create(['product' => 'Theirs', 'qty' => 1, 'price' => 1, 'subtotal' => 1]);

    OrderForm::make()->save(orderInput([
        Repeater::RECORD_KEY_PREFIX.$foreignItem->id => ['product' => 'Hijacked', 'qty' => 1, 'price' => 1],
    ]));

    expect($foreignItem->fresh()->product)->toBe('Theirs');
});

/**
 * Saves inside a transaction that fails once, as a retried deadlock would.
 */
function saveWithRetry(SimpleOrderForm $form, string $customer): void
{
    $attempt = 0;

    DB::transaction(function () use ($form, $customer, &$attempt): void {
        $form->save(['customer' => $customer]);

        if (++$attempt === 1) {
            throw new RuntimeException('Deadlock found when trying to get lock');
        }
    }, 2);
}

it('creates the record when a transaction retries the same form', function () {
    saveWithRetry(SimpleOrderForm::make(), 'Ada');

    expect(Order::query()->pluck('customer')->all())->toBe(['Ada']);
});

it('updates the record when a transaction retries the same form', function () {
    $order = Order::query()->create(['customer' => 'Ada']);

    saveWithRetry(SimpleOrderForm::make($order), 'Grace');

    expect($order->fresh()->customer)->toBe('Grace');
});
