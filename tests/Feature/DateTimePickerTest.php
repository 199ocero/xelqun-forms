<?php

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\DatePicker;
use Xelqun\Forms\Components\DateTimePicker;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TimePicker;
use Xelqun\Forms\Form;
use Xelqun\Forms\Tests\Fixtures\Order;
use Xelqun\Forms\Tests\Fixtures\OrderForm;

/**
 * @return array<string, mixed>
 */
function pickerProps(DateTimePicker $picker): array
{
    return (array) Form::make([$picker])->toSchema()['components'][0]['props'];
}

function datedOrder(string $at): Order
{
    $order = new Order;
    $order->exists = true;
    $order->created_at = Carbon::parse($at, 'UTC');

    return $order;
}

function pickerForm(Closure $picker): OrderForm
{
    $form = new class extends OrderForm
    {
        public static Closure $picker;

        public function schema(): array
        {
            return [(self::$picker)()];
        }
    };
    $form::$picker = $picker;

    return $form;
}

beforeEach(fn () => config(['app.timezone' => 'UTC']));

it('holds one state format and stores the default one', function (Closure $make, string $state, string $saved) {
    $form = fn (): Form => Form::make([$make()]);

    expect($form()->validate(['at' => $state]))->toBe(['at' => $saved]);
})->with([
    'date' => [fn () => DatePicker::make('at'), '2026-03-04', '2026-03-04'],
    'date time' => [fn () => DateTimePicker::make('at'), '2026-03-04 05:06:07', '2026-03-04 05:06:07'],
    'date time without seconds' => [fn () => DateTimePicker::make('at')->seconds(false), '2026-03-04 05:06', '2026-03-04 05:06:00'],
    'time' => [fn () => TimePicker::make('at'), '05:06:07', '05:06:07'],
    'time without seconds' => [fn () => TimePicker::make('at')->seconds(false), '05:06', '05:06:00'],
]);

it('refuses a value in another format', function (Closure $make, mixed $value) {
    expect(fn () => Form::make([$make()])->validate(['at' => $value]))->toThrow(ValidationException::class);
})->with([
    'date with a time' => [fn () => DatePicker::make('at'), '2026-03-04 05:06:07'],
    'date time without a time' => [fn () => DateTimePicker::make('at'), '2026-03-04'],
    'time with a date' => [fn () => TimePicker::make('at'), '2026-03-04 05:06:07'],
    'nonsense' => [fn () => DateTimePicker::make('at'), 'soon'],
    'array' => [fn () => DateTimePicker::make('at'), ['2026-03-04 05:06:07']],
]);

it('converts to and from the stored format', function () {
    $form = fn (): Form => Form::make([DateTimePicker::make('at')->format('d/m/Y H:i')->default('04/03/2026 05:06')]);

    expect($form()->getData()['at'])->toBe('2026-03-04 05:06:00')
        ->and($form()->validate(['at' => '2026-03-04 05:06:00']))->toBe(['at' => '04/03/2026 05:06']);
});

it('reads a date time from a model', function () {
    $form = pickerForm(fn () => DateTimePicker::make('created_at'));

    expect($form::make(datedOrder('2026-03-04 05:06:07'))->getData()['created_at'])->toBe('2026-03-04 05:06:07');
});

it('shows the timezone and stores the app one', function () {
    $form = pickerForm(fn () => DateTimePicker::make('created_at')->timezone('Asia/Manila'));
    $instance = $form::make(datedOrder('2026-01-01 20:00:00'));

    expect($instance->getData()['created_at'])->toBe('2026-01-02 04:00:00')
        ->and($instance->validate(['created_at' => '2026-01-02 04:00:00'])['created_at'])->toBe('2026-01-01 20:00:00');
});

it('converts a default once, inside an array row too', function () {
    $make = fn (): DateTimePicker => DateTimePicker::make('at')->timezone('Asia/Manila')->default(Carbon::parse('2026-01-01 00:00:00', 'UTC'));
    $form = Form::make([Repeater::make('rows')->default([['name' => 'x']])->schema([$make()])]);

    expect(array_values($form->getData()['rows'])[0]['at'])->toBe('2026-01-01 08:00:00')
        ->and(Form::make([$make()])->getData()['at'])->toBe('2026-01-01 08:00:00');
});

it('still converts a value the row does have', function () {
    $form = Form::make([
        Repeater::make('rows')->default([['at' => '2026-01-01 00:00:00']])->schema([
            DateTimePicker::make('at')->timezone('Asia/Manila'),
        ]),
    ]);

    expect(array_values($form->getData()['rows'])[0]['at'])->toBe('2026-01-01 08:00:00');
});

it('ignores a timezone on a field with only a date or a time', function () {
    $date = pickerForm(fn () => DatePicker::make('created_at')->timezone('Asia/Manila'));

    expect($date::make(datedOrder('2026-01-01 20:00:00'))->getData()['created_at'])->toBe('2026-01-01');

    $time = pickerForm(fn () => TimePicker::make('created_at')->timezone('Asia/Manila'));

    expect($time::make(datedOrder('2026-01-01 20:00:00'))->getData()['created_at'])->toBe('20:00:00');
});

it('checks limits in the shown timezone', function () {
    $make = fn (): Form => Form::make([
        DateTimePicker::make('at')
            ->timezone('Asia/Manila')
            ->minDate(Carbon::parse('2026-01-01 20:00:00', 'UTC')),
    ]);

    expect(pickerProps(DateTimePicker::make('at')->timezone('Asia/Manila')->minDate(Carbon::parse('2026-01-01 20:00:00', 'UTC')))['min'])->toBe('2026-01-02 04:00:00')
        ->and($make()->validate(['at' => '2026-01-02 04:00:00']))->toBe(['at' => '2026-01-01 20:00:00'])
        ->and(fn () => $make()->validate(['at' => '2026-01-02 03:59:59']))->toThrow(ValidationException::class);
});

it('takes a time as a limit on a time picker', function () {
    $make = fn (): Form => Form::make([TimePicker::make('at')->seconds(false)->minDate('09:00')->maxDate('17:00')]);

    expect($make()->validate(['at' => '09:00']))->toBe(['at' => '09:00:00'])
        ->and(fn () => $make()->validate(['at' => '08:59']))->toThrow(ValidationException::class)
        ->and(fn () => $make()->validate(['at' => '17:01']))->toThrow(ValidationException::class);
});

it('refuses a disabled date on the server', function () {
    $make = fn (Closure|array $dates): Form => Form::make([DateTimePicker::make('at')->disabledDates($dates)]);

    expect(fn () => $make(['2026-03-04'])->validate(['at' => '2026-03-04 05:06:07']))->toThrow(ValidationException::class)
        ->and(fn () => $make(fn (): array => [Carbon::parse('2026-03-04')])->validate(['at' => '2026-03-04 05:06:07']))->toThrow(ValidationException::class)
        ->and($make(['2026-03-04'])->validate(['at' => '2026-03-05 05:06:07']))->toBe(['at' => '2026-03-05 05:06:07']);
});

it('keeps an unreadable stored value instead of losing it', function () {
    $form = pickerForm(fn () => DateTimePicker::make('customer'));
    $order = new Order;
    $order->exists = true;
    $order->customer = 'not a date';

    expect($form::make($order)->getData()['customer'])->toBe('not a date');
});

it('fills a default date object', function () {
    $form = Form::make([DateTimePicker::make('at')->default(Carbon::parse('2026-03-04 05:06:07'))]);

    expect($form->getData()['at'])->toBe('2026-03-04 05:06:07');
});

it('tells the browser what it needs', function () {
    expect(pickerProps(DatePicker::make('at')))->toBe(['min' => null, 'max' => null])
        ->and(pickerProps(DateTimePicker::make('at')->seconds(false)->hoursStep(2)->minutesStep(15)))->toBe([
            'min' => null,
            'max' => null,
            'time' => true,
            'steps' => ['hour' => 2, 'minute' => 15, 'second' => 1],
        ])
        ->and(pickerProps(TimePicker::make('at')))->toMatchArray(['time' => true, 'timeOnly' => true, 'seconds' => true])
        ->and(pickerProps(DatePicker::make('at')->displayFormat('d F Y')->disabledDates(['2026-03-04'])))->toMatchArray([
            'displayFormat' => 'd F Y',
            'disabledDates' => ['2026-03-04'],
        ]);
});
