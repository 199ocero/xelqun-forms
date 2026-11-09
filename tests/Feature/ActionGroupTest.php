<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Xelqun\Actions\Action;
use Xelqun\Actions\ActionGroup;
use Xelqun\Actions\ActionSet;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Actions;
use Xelqun\Forms\Tests\Fixtures\Country;

class GroupedTestActions extends ActionSet
{
    public static array $ran = [];

    public static array $names = ['edit', 'archive'];

    public static bool $groupIsHidden = false;

    public function actions(): array
    {
        return [
            ActionGroup::make(array_map(
                fn (string $name): Action => Action::make($name)
                    ->authorize(true)
                    ->action(function () use ($name): void {
                        self::$ran[] = $name;
                    }),
                self::$names,
            ))->hidden(fn (): bool => self::$groupIsHidden),
            Action::make('secret')->action(fn () => null),
        ];
    }
}

class FullyHiddenTestActions extends ActionSet
{
    public function actions(): array
    {
        return [ActionGroup::make([Action::make('secret')->label('Secret label')->action(fn () => null)])];
    }
}

class DuplicateGroupTestActions extends ActionSet
{
    public function actions(): array
    {
        return [
            Action::make('edit')->authorize(true),
            ActionGroup::make([Action::make('edit')->authorize(true)]),
        ];
    }
}

class GroupPolicyTestActions extends ActionSet
{
    public function model(): string
    {
        return Country::class;
    }

    public function actions(): array
    {
        return [ActionGroup::make([Action::make('create')->authorize('create-country')])];
    }
}

class GroupInFormTestForm extends Form
{
    public static int $ran = 0;

    public function schema(): array
    {
        return [
            Actions::make([
                ActionGroup::make([
                    Action::make('ping')->authorize(true)->action(function (): void {
                        self::$ran++;
                    }),
                ])->label('More')->icon('settings')->color('danger')->tooltip('More things')->badge(3)->badgeColor('warning')->size('md'),
            ]),
        ];
    }
}

function clickGroupedAction(string $token, string $key): TestResponse
{
    return test()->postJson(route('xelqun.forms.action'), [
        'token' => $token,
        'action' => $key,
        'phase' => 'run',
        'state' => ['data' => []],
        'deps' => (object) [],
    ]);
}

beforeEach(function () {
    GroupedTestActions::$ran = [];
    GroupedTestActions::$names = ['edit', 'archive'];
    GroupedTestActions::$groupIsHidden = false;
});

it('runs an action from inside a group', function () {
    $schema = GroupedTestActions::make()->toSchema();
    $archive = $schema['components'][0]['children'][1];

    clickGroupedAction($schema['token'], $archive['key'])->assertOk();

    expect($archive['type'])->toBe('action')
        ->and(GroupedTestActions::$ran)->toBe(['archive']);
});

it('runs the action that was clicked, even when the group was reordered since', function () {
    $schema = GroupedTestActions::make()->toSchema();
    $archive = $schema['components'][0]['children'][1]['key'];

    GroupedTestActions::$names = ['archive', 'delete'];

    clickGroupedAction($schema['token'], $archive)->assertOk();

    expect(GroupedTestActions::$ran)->toBe(['archive']);
});

it('refuses a click on an action that left the group, with a 404', function () {
    $schema = GroupedTestActions::make()->toSchema();
    $edit = $schema['components'][0]['children'][0]['key'];

    GroupedTestActions::$names = ['delete', 'archive'];

    clickGroupedAction($schema['token'], $edit)->assertNotFound();

    expect(GroupedTestActions::$ran)->toBe([]);
});

it('refuses an action in a hidden group, with a 403', function () {
    $schema = GroupedTestActions::make()->toSchema();
    $edit = $schema['components'][0]['children'][0]['key'];

    GroupedTestActions::$groupIsHidden = true;

    clickGroupedAction($schema['token'], $edit)->assertForbidden();

    expect(GroupedTestActions::$ran)->toBe([]);
});

it('hides a group when the viewer can use none of its actions, and leaks nothing', function () {
    $schema = FullyHiddenTestActions::make()->toSchema();

    expect($schema['components'][0]['hidden'])->toBeTrue()
        ->and(json_encode($schema))->not->toContain('Secret label');
});

it('refuses two actions with one name, even when one is in a group', function () {
    DuplicateGroupTestActions::make()->toSchema();
})->throws(FormDefinitionException::class, 'two actions with the same name');

it('refuses a group inside a group', function () {
    ActionGroup::make([ActionGroup::make([])]);
})->throws(FormDefinitionException::class, 'can only hold actions');

it('asks a policy about the model of the set for an action in a group', function () {
    Gate::define('create-country', fn (?User $user, string $model): bool => $model === Country::class);

    expect(GroupPolicyTestActions::make()->toSchema()['components'][0]['hidden'])->toBeFalse();
});

it('works inside a form, and sends how the button should look', function () {
    GroupInFormTestForm::$ran = 0;

    $schema = GroupInFormTestForm::make()->toSchema();
    $group = $schema['components'][0]['children'][0];

    clickGroupedAction($schema['token'], $group['children'][0]['key'])->assertOk();

    expect(GroupInFormTestForm::$ran)->toBe(1)
        ->and((array) $group['props'])->toBe([
            'label' => 'More',
            'icon' => 'settings',
            'color' => 'danger',
            'tooltip' => 'More things',
            'badge' => 3,
            'badgeColor' => 'warning',
            'size' => 'md',
        ]);
});
