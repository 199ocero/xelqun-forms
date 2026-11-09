import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import type { SchemaNode, UploadResult } from '../../resources/js/schema/types';
import { field, schema } from './fixtures';

const { replace } = vi.hoisted(() => ({ replace: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ router: { replace } }));

function tabs(first: SchemaNode, second: SchemaNode, props = {}): SchemaNode {
    return {
        ...field('', 'tabs'),
        key: 'data#0',
        path: null,
        props,
        children: [
            {
                ...field('', 'tab'),
                key: 'data#0.0',
                path: null,
                label: 'First',
                props: { slug: 'first' },
                children: [first],
            },
            {
                ...field('', 'tab'),
                key: 'data#0.1',
                path: null,
                label: 'Second',
                props: { slug: 'second' },
                children: [second],
            },
        ],
    };
}

async function mountTabs(first: SchemaNode, second: SchemaNode, props = {}) {
    const store = createFormStore(schema([tabs(first, second, props)], {}, null));
    const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

    await vi.dynamicImportSettled();
    await flushPromises();

    return { store, wrapper };
}

/**
 * Reka opens a tab on mousedown, not click.
 */
async function openTab(wrapper: Awaited<ReturnType<typeof mountTabs>>['wrapper'], index: number) {
    await wrapper.findAll('[role="tab"]')[index].trigger('mousedown', { button: 0 });
    await flushPromises();
}

describe('tabs', () => {
    let mounted: ReturnType<typeof mount> | null = null;

    afterEach(() => {
        mounted?.unmount();
        replace.mockClear();
        window.history.replaceState(null, '', '/');
        localStorage.clear();
    });

    it('shows a tab badge', async () => {
        const store = createFormStore(
            schema([tabs(field('data.name'), field('data.city'))], {}, null),
        );
        const node = store.components[0];

        node.children![0].props = { slug: 'first', badge: 4, badgeColor: 'danger' };
        mounted = mount(XqForm, { props: { form: store }, attachTo: document.body });
        await flushPromises();

        const badge = mounted.find('[data-xq-tab-badge]');

        expect(badge.text()).toBe('4');
        expect(badge.classes()).toContain('bg-xq-destructive');
    });

    it('remembers the open tab for the next visit', async () => {
        const first = await mountTabs(field('data.name'), field('data.city'), { persistTab: true });

        await openTab(first.wrapper, 1);
        first.wrapper.unmount();

        const { wrapper } = await mountTabs(field('data.name'), field('data.city'), {
            persistTab: true,
        });
        mounted = wrapper;

        expect(wrapper.findAll('[role="tab"]')[1].attributes('aria-selected')).toBe('true');
    });

    it('opens the tab the server picked from the url', async () => {
        const { wrapper } = await mountTabs(field('data.name'), field('data.city'), {
            startKey: 'data#0.1',
        });
        mounted = wrapper;

        expect(wrapper.findAll('[role="tab"]')[1].attributes('aria-selected')).toBe('true');
    });

    it('puts the open tab in the url and keeps what else is there', async () => {
        window.history.replaceState(null, '', '/edit?page=2#notes');
        const { wrapper } = await mountTabs(field('data.name'), field('data.city'), {
            queryKey: 'tab',
        });
        mounted = wrapper;

        await openTab(wrapper, 1);

        expect(replace).toHaveBeenCalledWith({
            url: '/edit?page=2&tab=second#notes',
            preserveState: true,
            preserveScroll: true,
        });
    });

    it('leaves the url alone unless the tabs ask to be in it', async () => {
        const { wrapper } = await mountTabs(field('data.name'), field('data.city'));
        mounted = wrapper;

        await openTab(wrapper, 1);

        expect(replace).not.toHaveBeenCalled();
    });

    it('keeps an upload running while another tab is open', async () => {
        const { store, wrapper } = await mountTabs(
            field('data.document', 'file-upload'),
            field('data.name'),
        );
        mounted = wrapper;

        let signal: AbortSignal | undefined;
        let finish: (result: UploadResult) => void = () => {};

        vi.spyOn(store, 'upload').mockImplementation((_path, _file, _progress, abort) => {
            signal = abort;

            return new Promise((resolve) => (finish = resolve));
        });

        const input = wrapper.find<HTMLInputElement>('input[type="file"]');

        Object.defineProperty(input.element, 'files', { value: [new File(['x'], 'cv.pdf')] });
        await input.trigger('change');
        await flushPromises();

        await openTab(wrapper, 1);

        expect(wrapper.findAll('[role="tabpanel"]')[0].attributes('hidden')).toBeDefined();
        expect(signal?.aborted).toBe(false);

        finish({ token: 'xq-tmp:abc', name: 'cv.pdf', size: 1, mime: null });
        await flushPromises();

        expect(store.get('data.document')).toBe('xq-tmp:abc');
    });

    it('focuses a field only once its tab is showing', async () => {
        const { store, wrapper } = await mountTabs(field('data.name'), field('data.city'));
        mounted = wrapper;

        const focusedInsideHiddenPanel: boolean[] = [];

        vi.spyOn(HTMLElement.prototype, 'focus').mockImplementation(function (this: HTMLElement) {
            focusedInsideHiddenPanel.push(this.closest('[hidden]') !== null);
        });

        store.errors['data.city'] = 'Required';
        store.focusFirstError();
        await flushPromises();
        vi.restoreAllMocks();

        expect(focusedInsideHiddenPanel).toEqual([false]);
    });
});
