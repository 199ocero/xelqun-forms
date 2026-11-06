<?php

namespace Xelqun\Notifications;

use Inertia\Inertia;

/**
 * Gets notifications to the browser, the way each kind of request can.
 *
 * Our own endpoints answer with JSON, so they carry them in the response. A
 * request that fails never reads them, which keeps "Saved" off the screen after
 * a rollback. Anything else is an Inertia visit or a redirect to one, where
 * flash data lasts until the next page and doesn't come back with the back
 * button.
 *
 * @internal
 */
final class NotificationQueue
{
    public const string FLASH_KEY = 'xelqunNotifications';

    /** @var array<int, array<string, mixed>> */
    private array $pending = [];

    public function push(Notification $notification): void
    {
        if (request()->routeIs('xelqun.*')) {
            $this->pending[] = $notification->toArray();

            return;
        }

        Inertia::flash(self::FLASH_KEY, [...(Inertia::getFlashed()[self::FLASH_KEY] ?? []), $notification->toArray()]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pull(): array
    {
        $notifications = $this->pending;
        $this->pending = [];

        return $notifications;
    }
}
