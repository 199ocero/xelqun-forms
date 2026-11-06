import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import XqForm from '../../resources/js/components/XqForm.vue';
import XqCheckbox from '../../resources/js/ui/XqCheckbox.vue';
import { createFormStore } from '../../resources/js/core/store';
import { moveItem } from '../../resources/js/ui/options';
import { field, schema } from './fixtures';

async function render(type: string, props: Record<string, unknown>, value: unknown) {
    const store = createFormStore(schema([field('data.v', type, { props })], { v: value }));
    const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

    await vi.dynamicImportSettled();
    await flushPromises();
    await nextTick();

    return { store, wrapper };
}

const options = [
    { value: 'a', label: 'Alpha' },
    { value: 'b', label: 'Beta' },
    { value: 'c', label: 'Gamma', disabled: true },
];

afterEach(() => {
    document.body.innerHTML = '';
});

describe('moveItem', () => {
    it('moves within the list and stops at the ends', () => {
        expect(moveItem(['a', 'b', 'c'], 'b', 1)).toEqual(['a', 'c', 'b']);
        expect(moveItem(['a', 'b', 'c'], 'b', -1)).toEqual(['b', 'a', 'c']);
        expect(moveItem(['a', 'b'], 'a', -1)).toEqual(['a', 'b']);
        expect(moveItem(['a', 'b'], 'b', 1)).toEqual(['a', 'b']);
    });
});

describe('checkbox list', () => {
    it('adds and removes values', async () => {
        const { store, wrapper } = await render('checkbox-list', { options }, ['a']);
        const boxes = wrapper.findAll('button[role="checkbox"]');

        await boxes[1].trigger('click');
        expect(store.get('data.v')).toEqual(['a', 'b']);

        await boxes[0].trigger('click');
        expect(store.get('data.v')).toEqual(['b']);
        wrapper.unmount();
    });

    it('selects all but the disabled, and keeps the disabled when clearing', async () => {
        const { store, wrapper } = await render(
            'checkbox-list',
            { options, bulkToggleable: true },
            ['c'],
        );
        const buttons = wrapper
            .findAll('button')
            .filter((button) => /select all/i.test(button.text()));

        await buttons[0].trigger('click');
        expect(store.get('data.v')).toEqual(['c', 'a', 'b']);

        await buttons[1].trigger('click');
        expect(store.get('data.v')).toEqual(['c']);
        wrapper.unmount();
    });

    it('filters by label', async () => {
        const { wrapper } = await render('checkbox-list', { options, searchable: true }, []);

        await wrapper.find('input[type="search"]').setValue('bet');

        expect(wrapper.text()).toContain('Beta');
        expect(wrapper.text()).not.toContain('Alpha');
        wrapper.unmount();
    });
});

describe('toggle buttons', () => {
    it('picks one, and clears on a second press', async () => {
        const { store, wrapper } = await render('toggle-buttons', { options }, null);
        const [alpha] = wrapper.findAll('button');

        await alpha.trigger('click');
        expect(store.get('data.v')).toBe('a');

        await alpha.trigger('click');
        expect(store.get('data.v')).toBeNull();
        wrapper.unmount();
    });

    it('keeps a zero, which is a real value', async () => {
        const { store, wrapper } = await render(
            'toggle-buttons',
            {
                options: [
                    { value: 1, label: 'Yes' },
                    { value: 0, label: 'No' },
                ],
            },
            null,
        );

        await wrapper.findAll('button')[1].trigger('click');

        expect(store.get('data.v')).toBe(0);
        expect(wrapper.findAll('button')[1].attributes('data-state')).toBe('on');
        wrapper.unmount();
    });

    it('holds a list when multiple', async () => {
        const { store, wrapper } = await render('toggle-buttons', { options, multiple: true }, [
            'a',
        ]);

        await wrapper.findAll('button')[1].trigger('click');

        expect(store.get('data.v')).toEqual(['a', 'b']);
        wrapper.unmount();
    });
});

describe('tags input', () => {
    it('adds a tag on Enter and removes one', async () => {
        const { store, wrapper } = await render('tags-input', { splitKeys: [] }, ['a']);
        const input = wrapper.find('input');

        await input.setValue('b');
        await input.trigger('keydown', { key: 'Enter' });
        await nextTick();

        expect(store.get('data.v')).toEqual(['a', 'b']);

        await wrapper.find('[aria-label="Remove a"]').trigger('click');
        await nextTick();

        expect(store.get('data.v')).toEqual(['b']);
        wrapper.unmount();
    });

    it('only splits on a key the field asked for', async () => {
        const type = async (splitKeys: string[]) => {
            const { store, wrapper } = await render('tags-input', { splitKeys }, []);
            const input = wrapper.find('input').element as HTMLInputElement;

            input.value = 'Smith,';
            input.dispatchEvent(new InputEvent('input', { data: ',', bubbles: true }));
            await flushPromises();

            const tags = store.get('data.v');

            wrapper.unmount();

            return tags;
        };

        expect(await type([])).toEqual([]);
        expect(await type(['Tab'])).toEqual([]);
        expect(await type([','])).toEqual(['Smith']);
    });

    it('moves a tag with its buttons', async () => {
        const { store, wrapper } = await render('tags-input', { reorderable: true }, ['a', 'b']);

        await wrapper.find('[aria-label="Move b earlier"]').trigger('click');

        expect(store.get('data.v')).toEqual(['b', 'a']);
        wrapper.unmount();
    });

    it('shows a prefix without storing it', async () => {
        const { store, wrapper } = await render('tags-input', { tagPrefix: '#' }, ['a']);

        expect(wrapper.text()).toContain('#');
        expect(store.get('data.v')).toEqual(['a']);
        wrapper.unmount();
    });
});

describe('slider', () => {
    it('moves one handle and keeps a number', async () => {
        const { store, wrapper } = await render(
            'slider',
            { min: 0, max: 10, step: 2, tooltips: true },
            4,
        );
        const thumb = wrapper.find('[role="slider"]');

        expect(thumb.attributes('aria-valuenow')).toBe('4');

        await thumb.trigger('keydown', { key: 'ArrowRight' });
        await nextTick();

        expect(store.get('data.v')).toBe(6);
        expect(wrapper.find('output').text()).toBe('6');
        wrapper.unmount();
    });

    it('keeps a list with several handles', async () => {
        const { store, wrapper } = await render('slider', { min: 0, max: 10, step: 1 }, [2, 6]);
        const thumbs = wrapper.findAll('[role="slider"]');

        expect(thumbs).toHaveLength(2);

        (thumbs[1].element as HTMLElement).focus();
        await thumbs[1].trigger('keydown', { key: 'ArrowRight' });
        await nextTick();

        expect(store.get('data.v')).toEqual([2, 7]);
        wrapper.unmount();
    });
});

describe('key value', () => {
    const flags = { addable: true, deletable: true, editableKeys: true, editableValues: true };

    it('turns rows into a map, holding back a row with no key', async () => {
        const { store, wrapper } = await render(
            'key-value',
            { ...flags, keyLabel: 'Key', valueLabel: 'Value', addLabel: 'Add row' },
            [],
        );

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Add row')!
            .trigger('click');
        await nextTick();

        const [key, item] = wrapper.findAll('input');

        await item.setValue('1');
        expect(store.get('data.v')).toEqual({});

        await key.setValue('a');
        expect(store.get('data.v')).toEqual({ a: '1' });
        wrapper.unmount();
    });

    it('keeps typing focus when its own change comes back', async () => {
        const { wrapper } = await render('key-value', { ...flags }, { a: '1' });
        const input = wrapper.findAll('input')[1];

        await input.setValue('12');

        expect(wrapper.findAll('input')[1].element).toBe(input.element);
        wrapper.unmount();
    });

    it('follows a value from the server', async () => {
        const { store, wrapper } = await render('key-value', { ...flags }, { a: '1' });

        store.applyPatch(
            { $xq: 1, seq: 1, set: { 'data.v': { z: '9' } }, schema: {}, errors: {} },
            new Map(),
        );
        await nextTick();

        expect((wrapper.findAll('input')[0].element as HTMLInputElement).value).toBe('z');
        wrapper.unmount();
    });

    it('hides what its flags turn off', async () => {
        const { wrapper } = await render(
            'key-value',
            { addable: false, deletable: false, editableKeys: false, editableValues: true },
            { a: '1' },
        );

        expect(wrapper.find('[aria-label="Remove row 1"]').exists()).toBe(false);
        expect(wrapper.findAll('input')[0].attributes('disabled')).toBeDefined();
        expect(wrapper.findAll('input')[1].attributes('disabled')).toBeUndefined();
        wrapper.unmount();
    });
});

describe('color picker', () => {
    it('takes a typed value and shows it on the swatch', async () => {
        const { store, wrapper } = await render('color-picker', { format: 'hex' }, null);

        await wrapper.find('input').setValue('#ff0000');

        expect(store.get('data.v')).toBe('#ff0000');
        expect(wrapper.find('[aria-label^="Choose a color"]').attributes('aria-label')).toContain(
            '#ff0000',
        );
        wrapper.unmount();
    });

    it('paints the picker area', async () => {
        const { wrapper } = await render('color-picker', { format: 'hex' }, '#ff0000');

        await wrapper.find('[aria-label^="Choose a color"]').trigger('click');
        await flushPromises();
        await nextTick();

        const area = document.querySelector<HTMLElement>('[role="application"], .h-36');

        expect(area?.getAttribute('style')).toContain('gradient');
        wrapper.unmount();
    });

    it('survives a value that is not a color yet', async () => {
        const warn = vi.spyOn(console, 'error').mockImplementation(() => {});
        const { wrapper } = await render('color-picker', { format: 'hex' }, '#ff');

        await wrapper.find('input').setValue('#ff00');

        expect(warn).not.toHaveBeenCalled();
        wrapper.unmount();
        warn.mockRestore();
    });
});

describe('indeterminate checkbox', () => {
    it('shows the mixed state and turns on when clicked', async () => {
        const wrapper = mount(XqCheckbox, { props: { indeterminate: true, modelValue: false } });
        const box = wrapper.find('button[role="checkbox"]');

        expect(box.attributes('aria-checked')).toBe('mixed');

        await box.trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[true]]);
    });
});
