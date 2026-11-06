import { flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, h } from 'vue';
import type { App } from 'vue';
import { createFormStore } from '../../resources/js/core/store';
import { FLASH_KEY, notify } from '../../resources/js/core/notifications';
import { createXelqunForms } from '../../resources/js/plugin';
import type { XqNotificationAction } from '../../resources/js/schema/types';
import { schema } from './fixtures';

const { listeners, remove, reload, visit } = vi.hoisted(() => ({
    listeners: new Map<string, (event: unknown) => void>(),
    remove: vi.fn(),
    reload: vi.fn(),
    visit: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        reload,
        visit,
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

describe('notification actions', () => {
    let app: App;
    const run = { endpoint: '/_xelqun/forms/action', token: 'token', action: 'data#restore' };
    const undo = { label: 'Undo', close: true, run, failureMessage: 'That action could not run.' };

    async function showWith(actions: XqNotificationAction[]): Promise<HTMLElement> {
        app = installForms();
        await flushPromises();
        notify({ title: 'Deleted', persistent: true, actions });
        await flushPromises();

        return toasts()[0];
    }

    function press(toast: HTMLElement, label: string): void {
        [...toast.querySelectorAll<HTMLElement>('a, button')]
            .find((element) => element.textContent?.includes(label))!
            .click();
    }

    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn());
        reload.mockClear();
        visit.mockClear();
    });

    afterEach(() => {
        app.unmount();
        vi.unstubAllGlobals();
    });

    it('runs the action, shows what it reports, reloads and closes', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(
            new Response(JSON.stringify({ notifications: [{ title: 'Restored' }] })),
        );

        press(await showWith([undo]), 'Undo');
        await flushPromises();
        await flushPromises();

        const body = JSON.parse(vi.mocked(fetch).mock.calls[0][1]!.body as string);

        expect(body).toMatchObject({ token: 'token', action: 'data#restore', phase: 'run' });
        expect(reload).toHaveBeenCalledOnce();
        expect(toasts().map((toast) => toast.textContent)).toEqual([
            expect.stringContaining('Restored'),
        ]);
    });

    it('stays open and says why when the action fails', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(
            new Response(JSON.stringify({ errors: { data: ['Too late.'] } }), { status: 422 }),
        );

        const toast = await showWith([undo]);

        press(toast, 'Undo');
        await flushPromises();
        await flushPromises();

        expect(reload).not.toHaveBeenCalled();
        expect(toasts().some((item) => item.textContent?.includes('Deleted'))).toBe(true);
        expect(document.querySelector('[data-status="danger"]')?.textContent).toContain(
            'Too late.',
        );
    });

    it('runs once, however fast the button is pressed', async () => {
        vi.mocked(fetch).mockReturnValue(new Promise(() => {}));

        const toast = await showWith([undo]);

        press(toast, 'Undo');
        press(toast, 'Undo');
        await flushPromises();

        expect(fetch).toHaveBeenCalledOnce();
    });

    it('visits a link inside the app without a full page load', async () => {
        press(await showWith([{ label: 'View', url: '/orders/1', close: false }]), 'View');
        await flushPromises();

        expect(visit).toHaveBeenCalledWith('/orders/1');
        expect(toasts()).toHaveLength(1);
    });

    it('leaves a new tab to the browser', async () => {
        const toast = await showWith([
            { label: 'Docs', url: '/docs', openInNewTab: true, close: false },
        ]);

        toast.querySelector('a')!.addEventListener('click', (event) => event.preventDefault());
        press(toast, 'Docs');

        expect(visit).not.toHaveBeenCalled();
        expect(toast.querySelector('a')?.getAttribute('rel')).toBe('noopener noreferrer');
    });

    it('closes when the button has nothing else to do', async () => {
        press(await showWith([{ label: 'Dismiss', close: true }]), 'Dismiss');
        await flushPromises();
        await flushPromises();

        expect(toasts().filter((toast) => toast.dataset.state === 'open')).toHaveLength(0);
    });
});
