import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import type { SchemaNode } from '../../resources/js/schema/types';
import { field, schema } from './fixtures';

function repeater(
    templateNodes: SchemaNode[],
    rowKeys: string[],
    props: Record<string, unknown> = {},
    path = 'data.rows',
): SchemaNode {
    const template = JSON.parse(JSON.stringify(templateNodes));
    const rows = Object.fromEntries(
        rowKeys.map((key) => [
            key,
            JSON.parse(JSON.stringify(templateNodes).split('{row}').join(key)),
        ]),
    );

    return field(path, 'repeater', {
        containsRows: true,
        props: {
            template,
            rows,
            rowPlaceholder: '{row}',
            newRow: { name: null },
            itemLabel: 'Item',
            addLabel: 'Add item',
            orderable: true,
            ...props,
        },
    });
}

const nameField = () => field('data.rows.{row}.name', 'text-input', { label: 'Name' });

async function render(node: SchemaNode, data: Record<string, unknown>) {
    const store = createFormStore(schema([node], data));
    const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

    await vi.dynamicImportSettled();
    await flushPromises();

    return { store, wrapper };
}

const twoRows = { rows: { a: { name: 'Ada' }, b: { name: 'Grace' } } };

afterEach(() => {
    document.body.innerHTML = '';
});

describe('cloneable', () => {
    it('adds the copy right after its original', async () => {
        const { store, wrapper } = await render(
            repeater([nameField()], ['a', 'b'], { cloneable: true }),
            twoRows,
        );

        await wrapper.find('[data-xq-row="a"] [data-xq-clone]').trigger('click');
        await nextTick();

        const rows = store.get('data.rows');
        const keys = Object.keys(rows);

        expect(keys).toHaveLength(3);
        expect(keys[0]).toBe('a');
        expect(keys[2]).toBe('b');
        expect(rows[keys[1]]).toEqual({ name: 'Ada' });

        await nextTick();

        expect(
            wrapper.findAll('[data-xq-row]').map((row) => row.attributes('data-xq-row')),
        ).toEqual(keys);
        wrapper.unmount();
    });

    it('never shares a stored file or a nested record with the original', async () => {
        const inner = repeater(
            [field('data.rows.a.lines.{row}.qty', 'text-input')],
            ['record-5'],
            {},
            'data.rows.a.lines',
        );
        const node = repeater(
            [
                nameField(),
                field('data.rows.{row}.receipt', 'file-upload', {
                    acceptsUploads: true,
                    props: { files: {} },
                }),
                field('data.rows.{row}.group', 'fieldset', {
                    path: null,
                    children: [
                        field('data.rows.{row}.photo', 'file-upload', {
                            acceptsUploads: true,
                            props: { files: {} },
                        }),
                    ],
                }),
            ],
            ['a'],
            { cloneable: true },
        );

        node.props!.rows.a.push(inner);

        const { store, wrapper } = await render(node, {
            rows: {
                a: {
                    name: 'Ada',
                    receipt: 'x.pdf',
                    photo: 'y.png',
                    lines: { 'record-5': { qty: 2 } },
                },
            },
        });

        await wrapper.find('[data-xq-clone]').trigger('click');
        await nextTick();

        const rows = store.get('data.rows');
        const copy = rows[Object.keys(rows)[1]];

        expect(copy.name).toBe('Ada');
        expect(copy.receipt).toBeNull();
        expect(copy.photo).toBeNull();
        expect(Object.keys(copy.lines)).toHaveLength(1);
        expect(Object.keys(copy.lines)[0]).not.toBe('record-5');
        expect(Object.values(copy.lines)[0]).toEqual({ qty: 2 });
        expect(rows.a.receipt).toBe('x.pdf');
        expect(rows.a.lines['record-5']).toEqual({ qty: 2 });
        wrapper.unmount();
    });

    it('keeps what the server loaded for the row', async () => {
        const select = (key: string, options: unknown[]) =>
            field(`data.rows.${key}.country`, 'select', {
                props: { options, searchable: true, async: true },
            });
        const node = repeater([select('{row}', [])], [], { cloneable: true });

        node.props!.rows = { a: [select('a', [{ value: 12, label: 'Philippines' }])] };

        const { store, wrapper } = await render(node, { rows: { a: { country: 12 } } });

        await wrapper.find('[data-xq-clone]').trigger('click');
        await flushPromises();
        await nextTick();

        const copyKey = Object.keys(store.get('data.rows'))[1];
        const input = wrapper.find(`[data-xq-row="${copyKey}"] input`);

        expect((input.element as HTMLInputElement).value).toBe('Philippines');
        expect(store.nodes.has(`data.rows.${copyKey}.country`)).toBe(true);
        wrapper.unmount();
    });

    it('keeps the nodes of nested rows under their new keys', async () => {
        const inner = field('data.rows.a.lines', 'repeater', {
            containsRows: true,
            props: {
                template: [field('data.rows.a.lines.{row:1}.qty', 'text-input')],
                rows: { x: [field('data.rows.a.lines.x.qty', 'text-input', { label: 'Loaded' })] },
                rowPlaceholder: '{row:1}',
                newRow: {},
                itemLabel: 'Line',
                addLabel: 'Add',
                orderable: true,
            },
        });
        const node = repeater([], [], { cloneable: true });

        node.props!.rows = { a: [inner] };

        const { store, wrapper } = await render(node, {
            rows: { a: { lines: { x: { qty: 2 } } } },
        });

        await wrapper.find('[data-xq-clone]').trigger('click');
        await flushPromises();
        await nextTick();

        const copyKey = Object.keys(store.get('data.rows'))[1];
        const lineKey = Object.keys(store.get(`data.rows.${copyKey}.lines`))[0];

        expect(lineKey).not.toBe('x');
        expect(store.nodes.get(`data.rows.${copyKey}.lines.${lineKey}.qty`)?.label).toBe('Loaded');
        expect(store.nodes.has('data.rows.a.lines.x.qty')).toBe(true);
        wrapper.unmount();
    });

    it('respects the limit on rows', async () => {
        const { wrapper } = await render(
            repeater([nameField()], ['a', 'b'], { cloneable: true, maxItems: 2 }),
            twoRows,
        );

        expect(wrapper.find('[data-xq-clone]').attributes('disabled')).toBeDefined();
        wrapper.unmount();
    });
});

describe('collapsible', () => {
    const body = (wrapper: any, key: string) =>
        wrapper.find(`[data-xq-row="${key}"] .xq-grid`).element as HTMLElement;

    it('starts collapsed when asked and toggles a row', async () => {
        const { wrapper } = await render(
            repeater([nameField()], ['a', 'b'], { collapsible: true, collapsed: true }),
            twoRows,
        );

        expect(body(wrapper, 'a').style.display).toBe('none');

        await wrapper.find('[data-xq-row="a"] [data-xq-collapse]').trigger('click');

        expect(body(wrapper, 'a').style.display).toBe('');
        expect(body(wrapper, 'b').style.display).toBe('none');
        wrapper.unmount();
    });

    it('stays open while the error is being fixed', async () => {
        const { store, wrapper } = await render(
            repeater([nameField()], ['a'], { collapsible: true, collapsed: true }),
            { rows: { a: { name: null } } },
        );

        store.setErrors({ 'data.rows.a.name': 'Required' });
        await nextTick();
        await wrapper.find('[data-xq-row="a"] input').setValue('Ada');
        await nextTick();

        expect(store.errors['data.rows.a.name']).toBeUndefined();
        expect(body(wrapper, 'a').style.display).toBe('');
        wrapper.unmount();
    });

    it('opens a row with an error, so nobody hunts for it', async () => {
        const { store, wrapper } = await render(
            repeater([nameField()], ['a'], { collapsible: true, collapsed: true }),
            { rows: { a: { name: null } } },
        );

        store.setErrors({ 'data.rows.a.name': 'Required' });
        await nextTick();

        expect(body(wrapper, 'a').style.display).toBe('');
        wrapper.unmount();
    });

    it('has no toggle unless asked', async () => {
        const { wrapper } = await render(repeater([nameField()], ['a']), { rows: { a: {} } });

        expect(wrapper.find('[data-xq-collapse]').exists()).toBe(false);
        wrapper.unmount();
    });
});

describe('layouts', () => {
    it('shows a simple repeater with no row header', async () => {
        const { wrapper } = await render(repeater([nameField()], ['a'], { simple: true }), {
            rows: { a: { name: 'Ada' } },
        });

        expect(wrapper.find('[data-xq-row="a"] input').exists()).toBe(true);
        expect(wrapper.find('[data-xq-row="a"]').text()).not.toContain('Item 1');
        wrapper.unmount();
    });

    it('shows a table with a header and a cell per field', async () => {
        const { wrapper } = await render(
            repeater([nameField(), field('data.rows.{row}.role', 'text-input')], ['a', 'b'], {
                table: [
                    { label: 'Name', width: '200px', hiddenLabel: false, required: true },
                    { label: 'Role', width: null, hiddenLabel: true, required: false },
                ],
            }),
            { rows: { a: { name: 'Ada', role: 'x' }, b: { name: 'Grace', role: 'y' } } },
        );

        const headers = wrapper.findAll('th');

        expect(headers.map((header) => header.text())).toEqual(['Name*', 'Role', 'Actions']);
        expect(headers[0].attributes('style')).toContain('200px');
        expect(headers[0].attributes('scope')).toBe('col');
        expect(headers[1].find('.sr-only').exists()).toBe(true);
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        expect(wrapper.findAll('tbody tr')[0].findAll('td')).toHaveLength(3);
        wrapper.unmount();
    });

    it('lays rows out in a grid, and can drop the number', async () => {
        const { wrapper } = await render(
            repeater([nameField()], ['a', 'b'], {
                grid: { default: 1, md: 2 },
                itemNumbers: false,
            }),
            twoRows,
        );

        expect(
            wrapper.find('[data-xq-row="a"]').element.parentElement?.getAttribute('style'),
        ).toContain('--xq-cols-md: 2');
        expect(wrapper.find('[data-xq-row="a"]').text()).toContain('Item');
        expect(wrapper.find('[data-xq-row="a"]').text()).not.toContain('Item 1');
        wrapper.unmount();
    });
});

describe('addable and deletable', () => {
    it('hides what the server would refuse', async () => {
        const { wrapper } = await render(
            repeater([nameField()], ['a', 'b'], { addable: false, deletable: false }),
            twoRows,
        );

        expect(wrapper.find('[data-xq-add]').exists()).toBe(false);
        expect(wrapper.find('[data-xq-remove]').exists()).toBe(false);
        wrapper.unmount();
    });
});

describe('delete confirmation', () => {
    it('asks first, and only deletes on confirm', async () => {
        const { store, wrapper } = await render(
            repeater([nameField()], ['a', 'b'], { deleteConfirmation: 'Sure about this?' }),
            twoRows,
        );

        await wrapper.find('[data-xq-row="a"] [data-xq-remove]').trigger('click');
        await flushPromises();
        await nextTick();

        expect(document.body.textContent).toContain('Sure about this?');
        expect(Object.keys(store.get('data.rows'))).toEqual(['a', 'b']);

        const confirm = [
            ...document.querySelectorAll<HTMLButtonElement>('[role="alertdialog"] button'),
        ].find((button) => button.textContent?.trim() === 'Delete')!;

        confirm.click();
        await flushPromises();
        await nextTick();

        expect(Object.keys(store.get('data.rows'))).toEqual(['b']);
        wrapper.unmount();
    });

    it('keeps the row on cancel', async () => {
        const { store, wrapper } = await render(
            repeater([nameField()], ['a', 'b'], { deleteConfirmation: 'Sure?' }),
            twoRows,
        );

        await wrapper.find('[data-xq-row="a"] [data-xq-remove]').trigger('click');
        await flushPromises();
        await nextTick();

        const cancel = [
            ...document.querySelectorAll<HTMLButtonElement>('[role="alertdialog"] button'),
        ].find((button) => button.textContent?.trim() === 'Cancel')!;

        cancel.click();
        await flushPromises();
        await nextTick();

        expect(Object.keys(store.get('data.rows'))).toEqual(['a', 'b']);
        wrapper.unmount();
    });

    it('deletes straight away unless asked', async () => {
        const { store, wrapper } = await render(repeater([nameField()], ['a', 'b']), twoRows);

        await wrapper.find('[data-xq-row="a"] [data-xq-remove]').trigger('click');

        expect(Object.keys(store.get('data.rows'))).toEqual(['b']);
        wrapper.unmount();
    });
});

describe('drag and drop', () => {
    it('offers a handle only when a row can be dragged', async () => {
        const off = await render(
            repeater([nameField()], ['a', 'b'], { draggable: false }),
            twoRows,
        );

        expect(off.wrapper.find('[data-xq-drag]').exists()).toBe(false);
        off.wrapper.unmount();

        const alone = await render(repeater([nameField()], ['a'], { draggable: true }), {
            rows: { a: { name: '1' } },
        });

        expect(alone.wrapper.find('[data-xq-drag]').exists()).toBe(false);
        alone.wrapper.unmount();
    });

    it('keeps the move buttons for keyboard users', async () => {
        const { wrapper } = await render(
            repeater([nameField()], ['a', 'b'], { draggable: true }),
            twoRows,
        );

        expect(wrapper.find('[data-xq-drag]').exists()).toBe(true);
        expect(wrapper.find('[data-xq-move="1"]').exists()).toBe(true);
        wrapper.unmount();
    });
});

describe('nested rows', () => {
    it('adds a row at each level on its own path', async () => {
        const innerTemplate = [field('data.rows.{row}.lines.{row:1}.qty', 'text-input')];
        const inner = field('data.rows.{row}.lines', 'repeater', {
            containsRows: true,
            props: {
                template: innerTemplate,
                rows: {},
                rowPlaceholder: '{row:1}',
                newRow: { qty: 1 },
                itemLabel: 'Line',
                addLabel: 'Add line',
                orderable: true,
            },
        });
        const outer = field('data.rows', 'repeater', {
            containsRows: true,
            props: {
                template: [inner],
                rows: {},
                rowPlaceholder: '{row}',
                newRow: { lines: {} },
                itemLabel: 'Section',
                addLabel: 'Add section',
                orderable: true,
            },
        });
        const { store, wrapper } = await render(outer, { rows: {} });

        await wrapper.findAll('[data-xq-add]').at(-1)!.trigger('click');
        await nextTick();

        const sectionKey = Object.keys(store.get('data.rows'))[0];

        await wrapper.find(`[data-xq-row="${sectionKey}"] [data-xq-add]`).trigger('click');
        await nextTick();
        await nextTick();

        const lines = store.get(`data.rows.${sectionKey}.lines`);

        expect(Object.values(lines)).toEqual([{ qty: 1 }]);

        const lineKey = Object.keys(lines)[0];
        const input = wrapper.find(`input[name="data.rows.${sectionKey}.lines.${lineKey}.qty"]`);

        await input.setValue('7');

        expect(store.get(`data.rows.${sectionKey}.lines.${lineKey}.qty`)).toBe('7');
        wrapper.unmount();
    });
});
