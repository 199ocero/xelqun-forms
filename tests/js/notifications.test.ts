import { flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h } from 'vue';
import type { App } from 'vue';
import { createFormStore } from '../../resources/js/core/store';
import { FLASH_KEY, notify } from '../../resources/js/core/notifications';
import { createXelqunForms } from '../../resources/js/plugin';
import { schema } from './fixtures';

const { listeners, remove } = vi.hoisted(() => ({
    listeners: new Map<string, (event: unknown) => void>(),
    remove: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        on: (name: string, listener: (event: unknown) => void) => {
            listeners.set(name, listener);

            return remove;
        },
    },
}));

function installForms(options = {}): App {
    const app = createApp({ render: () => h('div') });

    app.use(createXelqunForms(options));
    app.mount(document.createElement('div'));

    return app;
}

function toasts(): HTMLElement[] {
    return [...document.querySelectorAll<HTMLElement>('[data-slot="xq-notifications"] li')];
}

describe('XqNotifications', () => {
    let app: App | undefined;

    afterEach(() => {
        app?.unmount();
        app = undefined;
        vi.useRealTimers();
    });

    it('shows a notification sent before the toaster was on the page', async () => {
        notify({ title: 'Early', body: 'Before the layout', status: 'success' });

        app = installForms();
        await flushPromises();

        expect(toasts()).toHaveLength(1);
        expect(toasts()[0].textContent).toContain('Early');
        expect(toasts()[0].textContent).toContain('Before the layout');
        expect(toasts()[0].getAttribute('data-status')).toBe('success');
    });

    it('renders text from a user as text', async () => {
        app = installForms();
        await flushPromises();

        notify({ title: '<img src=x onerror=alert(1)>' });
        await flushPromises();

        expect(toasts()[0].querySelector('img')).toBeNull();
        expect(toasts()[0].textContent).toContain('<img src=x onerror=alert(1)>');
    });

    it('announces trouble at once and everything else politely', async () => {
        vi.useFakeTimers();

        app = installForms();
        await flushPromises();

        notify({ title: 'Broken', status: 'danger' });
        notify({ title: 'Done', status: 'success' });
        await flushPromises();
        await vi.advanceTimersByTimeAsync(1100);

        expect(document.querySelector('[aria-live="assertive"]')?.textContent).toContain('Broken');
        expect(document.querySelector('[aria-live="polite"]')?.textContent).toContain('Done');
    });

    it('hides the oldest past the limit, so a bulk action cannot flood the screen', async () => {
        app = installForms();
        await flushPromises();

        for (let count = 1; count <= 7; count++) {
            notify({ title: `Sent ${count}`, persistent: true });
        }

        await flushPromises();

        const limited = toasts().filter((toast) => toast.hasAttribute('data-limited'));

        expect(toasts()).toHaveLength(7);
        expect(limited).toHaveLength(2);
    });

    it('labels the close button with the text the server sent', async () => {
        app = installForms();
        await flushPromises();

        notify({ title: 'Guardado', closeLabel: 'Cerrar' });
        await flushPromises();

        expect(toasts()[0].querySelector('button')?.getAttribute('aria-label')).toBe('Cerrar');
    });
});

describe('notifications from the server', () => {
    let app: App | undefined;

    afterEach(() => {
        app?.unmount();
        app = undefined;
        vi.useRealTimers();
        remove.mockClear();
    });

    it('shows what arrives in a patch', async () => {
        app = installForms();
        await flushPromises();

        createFormStore(schema([])).applyPatch(
            {
                $xq: 1,
                seq: 0,
                set: {},
                schema: {},
                errors: {},
                notifications: [{ title: 'Patched' }],
            },
            new Map(),
        );
        await flushPromises();

        expect(toasts()[0].textContent).toContain('Patched');
    });

    it('shows what arrives as flash data', async () => {
        app = installForms();
        await flushPromises();

        listeners.get('flash')!({ detail: { flash: { [FLASH_KEY]: [{ title: 'Flashed' }] } } });
        listeners.get('flash')!({ detail: { flash: { somethingElse: true } } });
        await flushPromises();

        expect(toasts().map((toast) => toast.textContent)).toEqual([
            expect.stringContaining('Flashed'),
        ]);
    });

    it('leaves nothing behind when the app goes away', async () => {
        const app = installForms({ notificationsLabel: 'Avisos ({hotkey})' });

        await flushPromises();

        expect(document.querySelector('[role="region"]')?.getAttribute('aria-label')).toContain(
            'Avisos',
        );

        app.unmount();

        expect(remove).toHaveBeenCalledOnce();
        expect(document.querySelector('[data-slot="xq-notifications"]')).toBeNull();
    });
});
