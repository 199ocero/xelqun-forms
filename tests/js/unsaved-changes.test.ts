import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import { field, schema } from './fixtures';

const inertia = vi.hoisted(() => ({
    handler: null as null | ((event: any) => void),
    stop: vi.fn(),
    visit: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        visit: inertia.visit,
        on: (_: string, handler: (event: any) => void) => {
            inertia.handler = handler;

            return inertia.stop;
        },
    },
}));

function visit(overrides = {}) {
    const event = {
        detail: {
            visit: {
                only: [],
                preserveState: false,
                prefetch: false,
                url: new URL(window.location.href),
                ...overrides,
            },
        },
        preventDefault: vi.fn(),
    };

    inertia.handler!(event);

    return event;
}

function total() {
    return field('data.total', 'text-input', {
        computed: { src: 'qty * 2', ast: { op: '*', args: [{ ref: 'data.qty' }, { lit: 2 }] } },
    });
}

async function mountForm(alert = true) {
    const store = createFormStore({
        ...schema(
            [field('data.name'), field('data.qty'), total()],
            { name: 'Ada', qty: 2, total: 0 },
            null,
        ),
        unsavedChangesAlert: alert,
    });
    const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

    await flushPromises();

    return { store, wrapper };
}

describe('unsaved changes alert', () => {
    const confirm = vi.fn();

    beforeEach(() => {
        confirm.mockReturnValue(false);
        vi.stubGlobal('confirm', confirm);
        window.confirm = confirm;
        inertia.handler = null;
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        confirm.mockReset();
        inertia.stop.mockReset();
        inertia.visit.mockReset();
    });

    it('stays out of the way unless the form asks for it', async () => {
        const { wrapper } = await mountForm(false);

        expect(inertia.handler).toBeNull();
        wrapper.unmount();
    });

    it('does not count a computed value settling as an edit', async () => {
        const { store, wrapper } = await mountForm();

        expect(store.get('data.total')).toBe(4);
        expect(store.isDirty()).toBe(false);
        expect(visit().preventDefault).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('asks before leaving with changes, and stays if the user says no', async () => {
        const { store, wrapper } = await mountForm();

        store.set('data.name', 'Grace');

        expect(visit().preventDefault).toHaveBeenCalledOnce();
        expect(confirm).toHaveBeenCalledWith('You have unsaved changes. Leave this page?');
        wrapper.unmount();
    });

    it('lets the user leave if they say yes', async () => {
        const { store, wrapper } = await mountForm();

        confirm.mockReturnValue(true);
        store.set('data.name', 'Grace');

        expect(visit().preventDefault).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('forgets a change the user undid', async () => {
        const { store, wrapper } = await mountForm();

        store.set('data.name', 'Grace');
        store.set('data.name', 'Ada');

        expect(store.isDirty()).toBe(false);
        wrapper.unmount();
    });

    it.each([
        ['a prefetch', { prefetch: true }],
        ['a partial reload', { only: ['stats'] }],
        ['a visit that keeps the page', { preserveState: true }],
    ])('does not ask for %s', async (_, overrides) => {
        const { store, wrapper } = await mountForm();

        store.set('data.name', 'Grace');

        expect(visit(overrides).preventDefault).not.toHaveBeenCalled();
        expect(confirm).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('asks when a visit that keeps state still goes to another page', async () => {
        const { store, wrapper } = await mountForm();

        store.set('data.name', 'Grace');

        const url = new URL('/other-page', window.location.href);

        expect(visit({ preserveState: true, url }).preventDefault).toHaveBeenCalledOnce();
        wrapper.unmount();
    });

    it('does not ask when the form itself is submitting', async () => {
        const { store, wrapper } = await mountForm();

        store.set('data.name', 'Grace');
        store.processing.value = true;

        expect(visit().preventDefault).not.toHaveBeenCalled();
        store.processing.value = false;
        wrapper.unmount();
    });

    it('counts the values as saved after a successful submit, but not a failed one', async () => {
        const { store, wrapper } = await mountForm();

        store.set('data.name', 'Grace');
        await store.submit('/save');
        inertia.visit.mock.calls[0][1].onError({ 'data.name': 'Nope' });
        inertia.visit.mock.calls[0][1].onFinish();

        expect(store.isDirty()).toBe(true);

        await store.submit('/save');
        inertia.visit.mock.calls[1][1].onSuccess({});
        inertia.visit.mock.calls[1][1].onFinish();

        expect(store.isDirty()).toBe(false);
        wrapper.unmount();
    });

    it('blocks the tab from closing only while there are changes', async () => {
        const { store, wrapper } = await mountForm();
        const close = () => {
            const event = new Event('beforeunload', { cancelable: true });

            window.dispatchEvent(event);

            return event.defaultPrevented;
        };

        expect(close()).toBe(false);

        store.set('data.name', 'Grace');

        expect(close()).toBe(true);
        wrapper.unmount();
    });

    it('stops listening when the form goes away', async () => {
        const { store, wrapper } = await mountForm();

        store.set('data.name', 'Grace');
        wrapper.unmount();

        const event = new Event('beforeunload', { cancelable: true });

        window.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
        expect(inertia.stop).toHaveBeenCalledOnce();
    });
});
