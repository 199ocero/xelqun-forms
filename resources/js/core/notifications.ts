import { router } from '@inertiajs/vue3';
import { createToastManager } from 'reka-ui';
import { isOwnUrl } from '../lib/utils';
import type { ActionResult, XqNotification, XqNotificationAction } from '../schema/types';
import { HttpError, postJson } from './http';

/** Where the server puts notifications for the next page. See NotificationQueue. */
export const FLASH_KEY = 'xelqunNotifications';

export const notificationManager =
    createToastManager<Pick<XqNotification, 'icon' | 'closeLabel' | 'actions'>>();

/**
 * Show a notification.
 */
export function notify(notification: XqNotification): void {
    const { title, body, status, icon, duration, persistent, actions, closeLabel } = notification;

    notificationManager.add({
        title: title ?? undefined,
        description: body ?? undefined,
        status: status ?? undefined,
        type: status === 'danger' || status === 'warning' ? 'foreground' : 'background',
        duration: persistent ? Number.POSITIVE_INFINITY : (duration ?? undefined),
        data: { icon, closeLabel, actions },
    });
}

/**
 * Press a button that runs an action, then do what any action does afterwards.
 * Says whether it worked, and a failure shows as a notification of its own.
 */
export async function runNotificationAction(action: XqNotificationAction): Promise<boolean> {
    const { endpoint, token, action: key } = action.run!;

    try {
        const result = await postJson<ActionResult>(endpoint, {
            token,
            action: key,
            phase: 'run',
            state: { data: {} },
            deps: {},
        });

        result.notifications?.forEach(notify);

        if (!result.redirect) {
            router.reload();
        } else if (isOwnUrl(result.redirect)) {
            router.visit(result.redirect);
        } else {
            window.location.assign(result.redirect);
        }

        return true;
    } catch (failure) {
        const [message] =
            failure instanceof HttpError ? Object.values(failure.validationErrors).flat() : [];

        notify({ title: message ?? action.failureMessage, status: 'danger' });

        return false;
    }
}
