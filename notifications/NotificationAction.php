<?php

namespace Xelqun\Notifications;

use Illuminate\Support\Str;
use Xelqun\Actions\ActionGroup;
use Xelqun\Actions\ActionSet;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Support\SafeUrl;

/**
 * A button on a notification. It opens a URL, closes the notification, or runs
 * an action of an ActionSet.
 *
 * Running takes a named action and not a closure, because a closure can't wait
 * in the browser. The click goes through the usual endpoint, which rebuilds the
 * set from its token and checks authorize() again.
 *
 * With nothing to do, the button just closes the notification.
 *
 * @api
 */
class NotificationAction
{
    protected ?string $label = null;

    protected ?string $icon = null;

    protected ?string $color = null;

    protected ?string $url = null;

    protected bool $opensUrlInNewTab = false;

    protected bool $closesNotification = false;

    protected ?ActionSet $actionSet = null;

    protected ?string $actionName = null;

    final public function __construct(protected string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * primary, gray or danger.
     */
    public function color(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * The button goes away when the URL isn't http(s), mailto, tel or a path on
     * this site, because it may be built from a record.
     */
    public function url(?string $url, bool $openInNewTab = false): static
    {
        $this->url = $url;
        $this->opensUrlInNewTab = $openInNewTab;

        return $this;
    }

    /**
     * Close the notification when the button is used.
     */
    public function close(bool $condition = true): static
    {
        $this->closesNotification = $condition;

        return $this;
    }

    /**
     * Run an action of this set, then close the notification and reload the
     * page. The button goes away when the viewer can't run the action. An action
     * with a modal or a confirmation has nowhere to open, so it's refused.
     */
    public function run(ActionSet $actionSet, string $action): static
    {
        $this->actionSet = $actionSet;
        $this->actionName = $action;

        return $this->close();
    }

    /**
     * @return array<string, mixed>|null Null when the button should not show.
     */
    public function toArray(): ?array
    {
        $url = SafeUrl::filter($this->url);

        if ($this->url !== null && $url === null) {
            return null;
        }

        $target = $this->actionSet === null ? [] : $this->getRunTarget($this->actionSet);

        if ($target === null) {
            return null;
        }

        return [
            'label' => $this->label ?? Str::headline($this->name),
            'icon' => $this->icon,
            'color' => $this->color,
            'close' => $this->closesNotification || ($url === null && $target === []),
            ...($url === null ? [] : ['url' => $url, 'openInNewTab' => $this->opensUrlInNewTab]),
            ...$target,
        ];
    }

    /**
     * @return array{run: array{endpoint: string, token: string, action: string}, failureMessage: string}|null
     */
    private function getRunTarget(ActionSet $actionSet): ?array
    {
        foreach (ActionGroup::flatten($actionSet->getComponents()) as $action) {
            if ($action->getName() !== $this->actionName) {
                continue;
            }

            if ($action->hasModalForm() || $action->needsConfirmation()) {
                throw new FormDefinitionException("The action [{$this->actionName}] has a modal or a confirmation, so a notification can't run it.");
            }

            return ! $action->canRun() ? null : [
                'run' => [
                    'endpoint' => route('xelqun.forms.action', absolute: false),
                    'token' => $actionSet->makeToken(),
                    'action' => $action->getKey(),
                ],
                'failureMessage' => trans('xelqun-forms::client.That action could not run.'),
            ];
        }

        throw new FormDefinitionException('The class ['.$actionSet::class."] has no action named [{$this->actionName}].");
    }
}
