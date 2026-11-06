<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Xelqun\Actions\Action;
use Xelqun\Actions\ActionSet;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Notifications\Notification;
use Xelqun\Notifications\NotificationQueue;

class NotifyingTestActions extends ActionSet
{
    public function actions(): array
    {
        return [
            Action::make('saved')
                ->authorize(true)
                ->successNotificationTitle('Saved')
                ->action(fn () => null),
            Action::make('named')
                ->authorize(true)
                ->successNotificationTitle(fn (array $data) => "Saved {$data['name']}")
                ->schema([TextInput::make('name')])
                ->action(fn () => null),
            Action::make('warned')
                ->authorize(true)
                ->action(fn () => Notification::make()->title('Careful')->body('Twice now')->warning()->duration(9000)->send()),
            Action::make('quiet')
                ->authorize(true)
                ->action(fn () => null),
            Action::make('refused')
                ->authorize(true)
                ->successNotificationTitle('Saved')
                ->action(function (): void {
                    Notification::make()->title('Halfway')->send();

                    throw ValidationException::withMessages(['data' => 'Not today.']);
                }),
        ];
    }
}

class NotifyingTestForm extends Form
{
    public function schema(): array
    {
        return [
            TextInput::make('name')->live()->afterStateUpdated(fn () => Notification::make()->title('Changed')->send()),
        ];
    }
}

function runNotifyingAction(string $name, ?array $data = null): TestResponse
{
    return test()->postJson(route('xelqun.forms.action'), [
        'token' => NotifyingTestActions::make()->makeToken(),
        'action' => "data#{$name}",
        'phase' => 'run',
        'state' => ['data' => []],
        'data' => $data,
        'deps' => (object) [],
    ]);
}

function visitAfterNotifying(int $times = 1): TestResponse
{
    Route::middleware('web')->post('/notify', function () use ($times) {
        foreach (range(1, $times) as $count) {
            Notification::make()->title("Sent {$count}")->send();
        }

        return redirect('/landing');
    });
    Route::middleware('web')->get('/landing', fn () => Inertia::render('Landing'));

    return test()->withHeader('X-Inertia', 'true')->followingRedirects()->post('/notify');
}

it('tells the user an action worked', function () {
    runNotifyingAction('saved')->assertOk()->assertJsonPath('notifications.0.title', 'Saved')->assertJsonPath('notifications.0.status', 'success');
});

it('can name what was changed in the title', function () {
    runNotifyingAction('named', ['name' => 'Peru'])->assertJsonPath('notifications.0.title', 'Saved Peru');
});

it('sends everything set on a notification', function () {
    expect(runNotifyingAction('warned')->json('notifications'))->toBe([[
        'title' => 'Careful',
        'body' => 'Twice now',
        'status' => 'warning',
        'icon' => null,
        'duration' => 9000,
        'persistent' => false,
        'closeLabel' => 'Close',
    ]]);
});

it('shows nothing from an action that was rolled back, now or on the next page', function () {
    runNotifyingAction('refused')->assertUnprocessable()->assertJsonMissingPath('notifications');

    Route::middleware('web')->get('/landing', fn () => Inertia::render('Landing'));

    test()->withHeader('X-Inertia', 'true')->get('/landing')->assertJsonMissingPath('flash');
});

it('leaves the key out of the patch when there is nothing to show', function () {
    runNotifyingAction('quiet')->assertOk()->assertJsonMissingPath('notifications');
});

it('reaches the browser from a hook on a live update', function () {
    test()->postJson(route('xelqun.forms.update'), [
        'token' => NotifyingTestForm::make()->makeToken(),
        'seq' => 1,
        'changed' => ['data.name'],
        'state' => ['data' => ['name' => 'Peru']],
        'deps' => (object) [],
    ])->assertOk()->assertJsonPath('notifications.0.title', 'Changed');
});

it('reaches the page after a redirect from a controller, once', function () {
    $response = visitAfterNotifying();

    expect($response->json('flash.'.NotificationQueue::FLASH_KEY.'.0.title'))->toBe('Sent 1');

    test()->withHeader('X-Inertia', 'true')->get('/landing')->assertJsonMissingPath('flash');
});

it('keeps every notification sent before a redirect', function () {
    $sent = visitAfterNotifying(2)->json('flash.'.NotificationQueue::FLASH_KEY);

    expect(array_column($sent, 'title'))->toBe(['Sent 1', 'Sent 2']);
});
