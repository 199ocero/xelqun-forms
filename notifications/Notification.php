<?php

namespace Xelqun\Notifications;

/**
 * A short message for the user, shown by XqNotifications. Send one from an
 * action, a hook or a controller, and it reaches the page either way.
 *
 * Its text renders as text, so a value from a user is safe in it.
 *
 * @api
 */
class Notification
{
    protected ?string $title = null;

    protected ?string $body = null;

    protected ?string $status = null;

    protected ?string $icon = null;

    protected ?int $duration = null;

    protected bool $isPersistent = false;

    /** @var array<int, NotificationAction> */
    protected array $actions = [];

    final public function __construct() {}

    public static function make(): static
    {
        return new static;
    }

    public function title(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function body(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function success(): static
    {
        $this->status = 'success';

        return $this;
    }

    public function danger(): static
    {
        $this->status = 'danger';

        return $this;
    }

    public function warning(): static
    {
        $this->status = 'warning';

        return $this;
    }

    public function info(): static
    {
        $this->status = 'info';

        return $this;
    }

    /**
     * Replace the status's icon. Any name the browser knows, as on an action.
     */
    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * How long it stays, in milliseconds.
     */
    public function duration(?int $milliseconds): static
    {
        $this->duration = $milliseconds;

        return $this;
    }

    /**
     * Keep it on screen until the user closes it.
     */
    public function persistent(bool $condition = true): static
    {
        $this->isPersistent = $condition;

        return $this;
    }

    /**
     * Buttons under the text. Add persistent() when people need time to use them.
     *
     * @param  array<int, NotificationAction>  $actions
     */
    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    public function send(): static
    {
        app(NotificationQueue::class)->push($this);

        return $this;
    }

    /**
     * The close label travels with it, because there's no form around the
     * toaster to bring translations.
     *
     * @return array{title: ?string, body: ?string, status: ?string, icon: ?string, duration: ?int, persistent: bool, actions: array<int, array<string, mixed>>, closeLabel: string}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'icon' => $this->icon,
            'duration' => $this->duration,
            'persistent' => $this->isPersistent,
            'actions' => array_values(array_filter(array_map(
                fn (NotificationAction $action): ?array => $action->toArray(),
                $this->actions,
            ))),
            'closeLabel' => trans('xelqun-forms::client.Close'),
        ];
    }
}
