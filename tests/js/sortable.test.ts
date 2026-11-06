import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';
import XqForm from '../../resources/js/components/XqForm.vue';
import { useSortable } from '../../resources/js/composables/useSortable';
import { createFormStore } from '../../resources/js/core/store';
import { field, schema } from './fixtures';

const created: Array<{ element: HTMLElement; options: any; destroy: ReturnType<typeof vi.fn> }> =
    [];

const library = vi.hoisted(() => ({ loaded: false }));

vi.mock('sortablejs', () => {
    library.loaded = true;

    return {
        default: {
            create: (element: HTMLElement, options: unknown) => {
                const instance = { element, options, destroy: vi.fn() };

                created.push(instance);

                return instance;
            },
        },
    };
});

beforeEach(() => {
    created.length = 0;
});

afterEach(() => {
    document.body.innerHTML = '';
});

function list(enabled = ref(true), onMove = vi.fn()) {
    const container = ref<HTMLElement | null>(null);
    const wrapper = mount(
        defineComponent({
            setup() {
                useSortable(container, { draggable: '> li', enabled: () => enabled.value, onMove });

                return () =>
                    h(
                        'ul',
                        { ref: container },
                        ['a', 'b', 'c'].map((item) => h('li', { key: item }, item)),
                    );
            },
        }),
        { attachTo: document.body },
    );

    return { wrapper, enabled, onMove };
}

/** What SortableJS does to the page before it reports a drag. */
function drop(from: number, to: number) {
    const { element, options } = created[0];
    const item = element.children[from];

    element.insertBefore(item, element.children[to + (to > from ? 1 : 0)] ?? null);
    options.onEnd({
        item,
        from: element,
        oldIndex: from,
        newIndex: to,
        oldDraggableIndex: from,
        newDraggableIndex: to,
    });
}

const order = () => [...created[0].element.children].map((child) => child.textContent);

describe('useSortable', () => {
    // First in the file, because the library stays loaded once a later test needs it.
    it('never downloads the library for a list that can not be dragged', async () => {
        const { wrapper } = list(ref(false));

        await vi.dynamicImportSettled();
        await flushPromises();

        expect(library.loaded).toBe(false);
        wrapper.unmount();
    });

    it('hands the page back to Vue after a drop, whichever way it went', async () => {
        const { wrapper, onMove } = list();

        await vi.dynamicImportSettled();
        await flushPromises();

        drop(0, 2);
        expect(order()).toEqual(['a', 'b', 'c']);
        expect(onMove).toHaveBeenLastCalledWith(0, 2);

        drop(2, 0);
        expect(order()).toEqual(['a', 'b', 'c']);
        expect(onMove).toHaveBeenLastCalledWith(2, 0);
        wrapper.unmount();
    });

    it('ignores a drop in the same place', async () => {
        const { wrapper, onMove } = list();

        await vi.dynamicImportSettled();
        await flushPromises();

        drop(1, 1);

        expect(onMove).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('loads nothing while it is off, and follows the switch', async () => {
        const enabled = ref(false);
        const { wrapper } = list(enabled);

        await vi.dynamicImportSettled();
        await flushPromises();
        expect(created).toHaveLength(0);

        enabled.value = true;
        await nextTick();
        await vi.dynamicImportSettled();
        await flushPromises();
        expect(created).toHaveLength(1);

        enabled.value = false;
        await nextTick();
        expect(created[0].destroy).toHaveBeenCalled();
        wrapper.unmount();
    });

    it('cleans up when the component goes', async () => {
        const { wrapper } = list();

        await vi.dynamicImportSettled();
        await flushPromises();
        wrapper.unmount();

        expect(created[0].destroy).toHaveBeenCalled();
    });

    it('always drags the same way, and never from an input', async () => {
        const { wrapper } = list();

        await vi.dynamicImportSettled();
        await flushPromises();

        expect(created[0].options).toMatchObject({ forceFallback: true, preventOnFilter: false });
        expect(created[0].options.filter).toContain('input');
        wrapper.unmount();
    });
});

describe('fields that can be dragged', () => {
    async function render(type: string, props: Record<string, unknown>, value: unknown) {
        const store = createFormStore(schema([field('data.v', type, { props })], { v: value }));
        const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

        await vi.dynamicImportSettled();
        await flushPromises();
        await nextTick();
        await vi.dynamicImportSettled();
        await flushPromises();

        return { store, wrapper };
    }

    const options = [
        { value: 'a', label: 'Alpha' },
        { value: 'b', label: 'Beta' },
        { value: 'c', label: 'Gamma' },
    ];

    it('reorders a multiple select', async () => {
        const { store, wrapper } = await render(
            'select',
            { options, multiple: true, reorderable: true },
            ['a', 'b', 'c'],
        );

        expect(created).toHaveLength(1);

        created[0].options.onEnd({
            item: created[0].element.children[0],
            from: created[0].element,
            oldIndex: 0,
            newIndex: 0,
            oldDraggableIndex: 0,
            newDraggableIndex: 2,
        });

        expect(store.get('data.v')).toEqual(['b', 'c', 'a']);
        wrapper.unmount();
    });

    it('reorders tags', async () => {
        const { store, wrapper } = await render('tags-input', { reorderable: true }, ['a', 'b']);

        created[0].options.onEnd({
            item: created[0].element.children[1],
            from: created[0].element,
            oldIndex: 1,
            newIndex: 1,
            oldDraggableIndex: 1,
            newDraggableIndex: 0,
        });

        expect(store.get('data.v')).toEqual(['b', 'a']);
        wrapper.unmount();
    });

    it('reorders key value rows', async () => {
        const { store, wrapper } = await render(
            'key-value',
            {
                reorderable: true,
                addable: true,
                deletable: true,
                editableKeys: true,
                editableValues: true,
            },
            { a: '1', b: '2' },
        );

        created[0].options.onEnd({
            item: created[0].element.children[1],
            from: created[0].element,
            oldIndex: 1,
            newIndex: 1,
            oldDraggableIndex: 1,
            newDraggableIndex: 0,
        });

        expect(Object.keys(store.get('data.v'))).toEqual(['b', 'a']);
        wrapper.unmount();
    });
});
