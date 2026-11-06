<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Xelqun\Actions\Action;
use Xelqun\Actions\ActionSet;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Notifications\Notification;
use Xelqun\Notifications\NotificationAction;
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
        'actions' => [],
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

class UndoTestActions extends ActionSet
{
    public static int $restored = 0;

    public static bool $isAllowed = true;

    public function actions(): array
    {
        return [
            Action::make('restore')
                ->authorize(fn (): bool => self::$isAllowed)
                ->successNotificationTitle('Restored')
                ->action(function (): void {
                    self::$restored++;
                }),
            Action::make('confirmed')->authorize(true)->requiresConfirmation(),
            Action::make('modal')->authorize(true)->schema([TextInput::make('reason')]),
        ];
    }
}

describe('notification actions', function () {
    beforeEach(function () {
        UndoTestActions::$restored = 0;
        UndoTestActions::$isAllowed = true;
    });

    it('runs an action through the usual endpoint, and reports back', function () {
        $button = NotificationAction::make('undo')->run(UndoTestActions::make(), 'restore')->toArray();

        test()->postJson($button['run']['endpoint'], [
            'token' => $button['run']['token'],
            'action' => $button['run']['action'],
            'phase' => 'run',
            'state' => ['data' => []],
            'deps' => (object) [],
        ])->assertOk()->assertJsonPath('notifications.0.title', 'Restored');

        expect(UndoTestActions::$restored)->toBe(1)
            ->and($button['close'])->toBeTrue();
    });

    it('still checks the action when the button is pressed, with a 403', function () {
        $button = NotificationAction::make('undo')->run(UndoTestActions::make(), 'restore')->toArray();

        UndoTestActions::$isAllowed = false;

        test()->postJson($button['run']['endpoint'], [
            'token' => $button['run']['token'],
            'action' => $button['run']['action'],
            'phase' => 'run',
            'state' => ['data' => []],
            'deps' => (object) [],
        ])->assertForbidden();

        expect(UndoTestActions::$restored)->toBe(0);
    });

    it('leaves out a button the viewer could not use', function () {
        UndoTestActions::$isAllowed = false;

        $notification = Notification::make()->actions([
            NotificationAction::make('undo')->run(UndoTestActions::make(), 'restore'),
        ]);

        expect($notification->toArray()['actions'])->toBe([]);
    });

    it('refuses an action that needs a modal or a confirmation', function (string $action) {
        NotificationAction::make('undo')->run(UndoTestActions::make(), $action)->toArray();
    })->with(['confirmed', 'modal'])->throws(FormDefinitionException::class, 'a modal or a confirmation');

    it('refuses an action the set does not have', function () {
        NotificationAction::make('undo')->run(UndoTestActions::make(), 'missing')->toArray();
    })->throws(FormDefinitionException::class, 'no action named [missing]');

    it('leaves out a link that is not safe', function () {
        $notification = Notification::make()->actions([
            NotificationAction::make('evil')->url('javascript:alert(1)'),
            NotificationAction::make('view')->url('/orders/1'),
        ]);

        expect(array_column($notification->toArray()['actions'], 'label'))->toBe(['View']);
    });

    it('closes the notification only when told to, or when there is nothing else to do', function () {
        expect(NotificationAction::make('view')->url('/orders')->toArray()['close'])->toBeFalse()
            ->and(NotificationAction::make('view')->url('/orders')->close()->toArray()['close'])->toBeTrue()
            ->and(NotificationAction::make('dismiss')->toArray()['close'])->toBeTrue();
    });
});
