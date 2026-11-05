import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import type { SchemaNode, UploadResult } from '../../resources/js/schema/types';
import { field, schema } from './fixtures';

function tabs(first: SchemaNode, second: SchemaNode): SchemaNode {
    return {
        ...field('', 'tabs'),
        key: 'data#0',
        path: null,
        children: [
            { ...field('', 'tab'), key: 'data#0.0', path: null, label: 'First', children: [first] },
            {
                ...field('', 'tab'),
                key: 'data#0.1',
                path: null,
                label: 'Second',
                children: [second],
            },
        ],
    };
}

async function mountTabs(first: SchemaNode, second: SchemaNode) {
    const store = createFormStore(schema([tabs(first, second)], {}, null));
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

    afterEach(() => mounted?.unmount());

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
