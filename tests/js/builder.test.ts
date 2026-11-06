import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import type { BlockOption, SchemaNode } from '../../resources/js/schema/types';
import { field, schema } from './fixtures';

vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn() } }));

function blockNode(
    row: string,
    name: string,
    children: SchemaNode[],
    preview: string | null = null,
): SchemaNode {
    return {
        key: `data.content.${row}#1`,
        type: 'block',
        hidden: false,
        path: null,
        props: { preview, name },
        children,
    };
}

function template(name: string, fieldName: string): SchemaNode[] {
    return [
        field('data.content.{row}.type', 'hidden'),
        blockNode('{row}', name, [field(`data.content.{row}.data.${fieldName}`)]),
    ];
}

const blocks: BlockOption[] = [
    {
        name: 'heading',
        label: 'Heading',
        icon: null,
        group: 'Text',
        maxItems: 1,
        template: template('heading', 'title'),
        newRow: { type: 'heading', data: { title: 'New' } },
    },
    {
        name: 'quote',
        label: 'Quote',
        icon: null,
        group: null,
        maxItems: null,
        template: template('quote', 'author'),
        newRow: { type: 'quote', data: { author: null } },
    },
];

function builder(rows: Record<string, SchemaNode[]> = {}, props = {}): SchemaNode {
    return field('data.content', 'builder', {
        containsRows: true,
        props: {
            blocks,
            rows,
            template: [],
            rowPlaceholder: '{row}',
            itemLabel: 'Item',
            addLabel: 'Add block',
            addable: true,
            orderable: true,
            ...props,
        },
    });
}

async function mountBuilder(data: Record<string, unknown> = {}, rows = {}, props = {}) {
    const store = createFormStore(schema([builder(rows, props)], { content: data }));
    const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

    await flushPromises();

    return { store, wrapper };
}

const pickerButton = (name: string) =>
    document.body.querySelector<HTMLButtonElement>(`[data-xq-block="${name}"]`);

async function openPicker(wrapper: any) {
    await wrapper.find('[data-xq-add]').trigger('click');
    await flushPromises();
}

describe('builder', () => {
    it('lists the blocks under their groups, with the ungrouped ones first', async () => {
        const { wrapper } = await mountBuilder();

        await openPicker(wrapper);

        const buttons = [...document.body.querySelectorAll('[data-xq-block]')];

        expect(buttons.map((item) => item.textContent?.trim())).toEqual(['Quote', 'Heading']);
        wrapper.unmount();
    });

    it('adds a row from the block that was picked, with its own fields', async () => {
        const { store, wrapper } = await mountBuilder();

        await openPicker(wrapper);
        pickerButton('heading')!.click();
        await flushPromises();

        const [key] = store.rowKeys(store.nodes.get('data.content')!);

        expect(store.get(`data.content.${key}`)).toEqual({
            type: 'heading',
            data: { title: 'New' },
        });
        expect(wrapper.find(`[data-xq-path="data.content.${key}.data.title"]`).exists()).toBe(true);
        expect(wrapper.find(`[data-xq-path="data.content.${key}.data.author"]`).exists()).toBe(
            false,
        );
        wrapper.unmount();
    });

    it('titles a row after its block', async () => {
        const { wrapper } = await mountBuilder();

        await openPicker(wrapper);
        pickerButton('quote')!.click();
        await flushPromises();

        expect(wrapper.find('[data-xq-row]').text()).toContain('Quote 1');
        wrapper.unmount();
    });

    it('stops offering a block at its limit', async () => {
        const { wrapper } = await mountBuilder({ r1: { type: 'heading', data: { title: 'x' } } });

        await openPicker(wrapper);

        expect(pickerButton('heading')!.disabled).toBe(true);
        expect(pickerButton('quote')!.disabled).toBe(false);
        wrapper.unmount();
    });

    it('shows a preview, and the form on request', async () => {
        const rows = {
            r1: [blockNode('r1', 'quote', [field('data.content.r1.data.author')], '<q>Hi</q>')],
        };
        const { wrapper } = await mountBuilder(
            { r1: { type: 'quote', data: { author: 'Ada' } } },
            rows,
        );
        const preview = wrapper.find('[data-xq-block-preview]');
        const fields = wrapper.find('[data-xq-path="data.content.r1.data.author"]');

        expect(preview.isVisible()).toBe(true);
        expect(preview.html()).toContain('<q>Hi</q>');
        expect(fields.isVisible()).toBe(false);

        await wrapper.find('[data-xq-block-preview] + div button').trigger('click');

        expect(preview.isVisible()).toBe(false);
        expect(fields.isVisible()).toBe(true);
        wrapper.unmount();
    });

    it('shows the form of a row with no preview yet', async () => {
        const rows = { r1: [blockNode('r1', 'quote', [field('data.content.r1.data.author')])] };
        const { wrapper } = await mountBuilder(
            { r1: { type: 'quote', data: { author: null } } },
            rows,
        );

        expect(wrapper.find('[data-xq-block-preview]').exists()).toBe(false);
        expect(wrapper.find('[data-xq-path="data.content.r1.data.author"]').isVisible()).toBe(true);
        wrapper.unmount();
    });

    it('opens the form of a previewed block that has an error', async () => {
        const rows = {
            r1: [blockNode('r1', 'quote', [field('data.content.r1.data.author')], '<q>Hi</q>')],
        };
        const { store, wrapper } = await mountBuilder(
            { r1: { type: 'quote', data: { author: null } } },
            rows,
        );
        const fields = wrapper.find('[data-xq-path="data.content.r1.data.author"]');

        store.setErrors({ 'data.content.r1.data.author': 'Bad' });
        await flushPromises();

        expect(fields.isVisible()).toBe(true);
        wrapper.unmount();
    });

    it('copies a row with its block', async () => {
        const rows = { r1: [blockNode('r1', 'quote', [field('data.content.r1.data.author')])] };
        const { store, wrapper } = await mountBuilder(
            { r1: { type: 'quote', data: { author: 'Ada' } } },
            rows,
            {
                cloneable: true,
            },
        );
        const node = store.nodes.get('data.content')!;
        const copy = store.cloneRow(node, 'r1');

        expect(store.get(`data.content.${copy}`)).toEqual({
            type: 'quote',
            data: { author: 'Ada' },
        });
        wrapper.unmount();
    });

    it('posts the state with an upload, so the server can find the block', async () => {
        const send = vi.fn();
        const FakeRequest = class {
            upload = { addEventListener: vi.fn() };
            addEventListener = vi.fn();
            open = vi.fn();
            setRequestHeader = vi.fn();
            send = send;
        };

        vi.stubGlobal('XMLHttpRequest', FakeRequest);

        const store = createFormStore(schema([], { content: { r1: { type: 'image', data: {} } } }));

        void store.upload('data.content.r1.data.file', new File(['x'], 'a.png'), () => {});

        const body = send.mock.calls[0][0] as FormData;

        expect(JSON.parse(body.get('state') as string).content.r1.type).toBe('image');
        vi.unstubAllGlobals();
    });
});
