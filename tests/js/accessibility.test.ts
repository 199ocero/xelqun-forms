import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import type { SchemaNode } from '../../resources/js/schema/types';
import { field, schema } from './fixtures';

const options = [
    { value: 'a', label: 'A' },
    { value: 'b', label: 'B' },
];

async function mountFields(components: SchemaNode[], data: Record<string, any> = {}, props = {}) {
    const store = createFormStore(schema(components, data, null));
    const wrapper = mount(XqForm, { props: { form: store, ...props }, attachTo: document.body });

    // Date pickers and file uploads are lazy-loaded.
    await vi.dynamicImportSettled();
    await flushPromises();

    return { store, wrapper };
}

const fields: Array<[string, SchemaNode]> = [
    ['text input', field('data.value')],
    ['textarea', field('data.value', 'textarea')],
    ['select', field('data.value', 'select', { props: { options } })],
    ['searchable select', field('data.value', 'select', { props: { options, searchable: true } })],
    ['date picker', field('data.value', 'date-picker')],
    ['radio', field('data.value', 'radio', { props: { options } })],
    ['checkbox', field('data.value', 'checkbox')],
    ['toggle', field('data.value', 'toggle')],
    ['file upload', field('data.value', 'file-upload')],
];

describe('form accessibility', () => {
    it.each(fields)('links the error to the %s control', async (_name, node) => {
        const { store, wrapper } = await mountFields([node], { value: null });

        store.errors['data.value'] = 'Something is wrong.';
        await nextTick();

        const control = wrapper.find('[aria-invalid="true"]');
        const message = wrapper.find('[role="alert"]');

        expect(control.exists()).toBe(true);
        expect(control.attributes('aria-describedby')).toBe(message.attributes('id'));
        wrapper.unmount();
    });

    it.each(fields)('links the hint to the %s control', async (_name, node) => {
        const { wrapper } = await mountFields([{ ...node, hint: 'A useful hint.' }], {
            value: null,
        });
        const hint = wrapper.findAll('p').find((element) => element.text() === 'A useful hint.');

        expect(hint?.attributes('id')).toBeTruthy();
        expect(wrapper.find(`[aria-describedby="${hint?.attributes('id')}"]`).exists()).toBe(true);
        wrapper.unmount();
    });

    it.each(fields)('tells screen readers the %s is required', async (_name, node) => {
        const { wrapper } = await mountFields([{ ...node, required: true }], { value: null });

        expect(wrapper.find('label .sr-only').text()).toBe('(required)');
        wrapper.unmount();
    });

    it('names the radio group after its label', async () => {
        const { wrapper } = await mountFields(
            [field('data.value', 'radio', { props: { options } })],
            {
                value: null,
            },
        );
        const label = wrapper.find('label');

        expect(wrapper.find('[role="radiogroup"]').attributes('aria-labelledby')).toBe(
            label.attributes('id'),
        );
        wrapper.unmount();
    });

    it('starts section titles at h2 and nests them one level deeper', async () => {
        const inner = field('inner', 'section', { path: null, label: 'Inner' });
        const outer = field('outer', 'section', { path: null, label: 'Outer', children: [inner] });
        const { wrapper } = await mountFields([outer]);

        expect(wrapper.find('h2').text()).toBe('Outer');
        expect(wrapper.find('h3').text()).toBe('Inner');
        wrapper.unmount();
    });

    it('lets the page set the section heading level', async () => {
        const section = field('outer', 'section', { path: null, label: 'Outer' });
        const { wrapper } = await mountFields([section], {}, { headingLevel: 4 });

        expect(wrapper.find('h4').text()).toBe('Outer');
        wrapper.unmount();
    });

    it('announces upload progress', async () => {
        const { store, wrapper } = await mountFields([field('data.value', 'file-upload')], {
            value: null,
        });

        vi.spyOn(store, 'upload').mockImplementation((_path, _file, onProgress) => {
            onProgress(40);

            return new Promise(() => {});
        });

        const input = wrapper.find<HTMLInputElement>('input[type="file"]');

        Object.defineProperty(input.element, 'files', { value: [new File(['x'], 'cv.pdf')] });
        await input.trigger('change');
        await flushPromises();

        const bar = wrapper.find('[role="progressbar"]');

        expect(bar.attributes('aria-valuenow')).toBe('40');
        expect(bar.attributes('aria-label')).toBe('Uploading cv.pdf');
        wrapper.unmount();
    });
});

describe('repeater keyboard focus', () => {
    async function mountRepeater(rowCount: number, minItems = 0) {
        const template = [field('data.items.{row}.name')];
        const keys = Array.from({ length: rowCount }, (_, index) => `row-${index}`);
        const rows = Object.fromEntries(
            keys.map((key) => [key, [field(`data.items.${key}.name`)]]),
        );
        const repeater = field('data.items', 'repeater', {
            props: {
                template,
                rows,
                itemLabel: 'Item',
                addLabel: 'Add',
                orderable: true,
                minItems,
            },
        });

        return mountFields([repeater], {
            items: Object.fromEntries(keys.map((key) => [key, { name: null }])),
        });
    }

    const focused = () => document.activeElement?.getAttribute('aria-label');

    it('moves focus to the row that takes the place of a removed one', async () => {
        const { wrapper } = await mountRepeater(3);

        await wrapper.find('[aria-label="Remove Item 1"]').trigger('click');
        await flushPromises();

        expect(focused()).toBe('Remove Item 1');
        wrapper.unmount();
    });

    it('moves focus to the last row when the last one is removed', async () => {
        const { wrapper } = await mountRepeater(3);

        await wrapper.find('[aria-label="Remove Item 3"]').trigger('click');
        await flushPromises();

        expect(focused()).toBe('Remove Item 2');
        wrapper.unmount();
    });

    it('moves focus to the add button when no row can take it', async () => {
        const { wrapper } = await mountRepeater(1);

        await wrapper.find('[aria-label="Remove Item 1"]').trigger('click');
        await flushPromises();

        expect(document.activeElement?.hasAttribute('data-xq-add')).toBe(true);
        wrapper.unmount();
    });

    it('keeps focus on the other arrow when a row reaches the top', async () => {
        const { wrapper } = await mountRepeater(2);

        await wrapper.find('[aria-label="Move Item 2 up"]').trigger('click');
        await flushPromises();

        expect(focused()).toBe('Move Item 1 down');
        wrapper.unmount();
    });
});
