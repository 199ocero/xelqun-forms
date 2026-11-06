import { createToastManager } from 'reka-ui';
import type { XqNotification } from '../schema/types';

/** Where the server puts notifications for the next page. See NotificationQueue. */
export const FLASH_KEY = 'xelqunNotifications';

export const notificationManager =
    createToastManager<Pick<XqNotification, 'icon' | 'closeLabel'>>();

/**
 * Show a notification.
 */
export function notify(notification: XqNotification): void {
    const { title, body, status, icon, duration, persistent, closeLabel } = notification;

    notificationManager.add({
        title: title ?? undefined,
        description: body ?? undefined,
        status: status ?? undefined,
        type: status === 'danger' || status === 'warning' ? 'foreground' : 'background',
        duration: persistent ? Number.POSITIVE_INFINITY : (duration ?? undefined),
        data: { icon, closeLabel },
    });
}
