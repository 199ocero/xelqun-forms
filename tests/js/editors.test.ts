import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import XqCodeEditorField from '../../resources/js/components/fields/XqCodeEditorField.vue';
import XqMarkdownEditorField from '../../resources/js/components/fields/XqMarkdownEditorField.vue';
import XqRichEditorField from '../../resources/js/components/fields/XqRichEditorField.vue';
import { FORM_STORE } from '../../resources/js/composables/context';
import { insertBlock, prefixLines, wrapSelection } from '../../resources/js/core/markdown';
import { createFormStore } from '../../resources/js/core/store';
import type { SchemaNode } from '../../resources/js/schema/types';
import { field, schema } from './fixtures';

vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn() } }));

async function mountField(component: any, node: SchemaNode, value: unknown = null) {
    const store = createFormStore(schema([node], { body: value }));
    const wrapper = mount(component, {
        props: { node: store.nodes.get('data.body')! },
        global: { provide: { [FORM_STORE as symbol]: store } },
        attachTo: document.body,
    });

    await flushPromises();

    return { store, wrapper };
}

describe('markdown edits', () => {
    it('wraps the selection and keeps it selected', () => {
        expect(wrapSelection('a b c', 2, 3, '**', '**')).toEqual({
            text: 'a **b** c',
            selectionStart: 4,
            selectionEnd: 5,
        });
    });

    it('selects a placeholder when nothing is selected', () => {
        expect(wrapSelection('', 0, 0, '*', '*', 'Text')).toEqual({
            text: '*Text*',
            selectionStart: 1,
            selectionEnd: 5,
        });
    });

    it('prefixes every line the selection touches', () => {
        expect(prefixLines('one\ntwo\nthree', 1, 5, '- ').text).toBe('- one\n- two\nthree');
    });

    it('numbers an ordered list', () => {
        expect(prefixLines('a\nb', 0, 3, '#').text).toBe('1. a\n2. b');
    });

    it('prefixes the only line of an empty text', () => {
        expect(prefixLines('', 0, 0, '> ').text).toBe('> ');
    });

    it('puts a block on lines of its own', () => {
        expect(insertBlock('text', 4, 'block').text).toBe('text\n\nblock\n');
        expect(insertBlock('', 0, 'block').text).toBe('block\n');
    });
});

describe('markdown editor', () => {
    const node = () =>
        field('data.body', 'markdown-editor', {
            props: { toolbar: [['bold', 'attachFiles']] },
            children: [
                {
                    key: 'data#0.s0',
                    type: 'action',
                    hidden: false,
                    path: null,
                    slot: 'markdownPreview',
                    props: {},
                },
            ],
        });

    afterEach(() => vi.unstubAllGlobals());

    it('writes to the form as the user types', async () => {
        const { store, wrapper } = await mountField(XqMarkdownEditorField, node());

        await wrapper.find('textarea').setValue('# Hi');

        expect(store.get('data.body')).toBe('# Hi');
        wrapper.unmount();
    });

    it('applies a toolbar button to the selection', async () => {
        const { store, wrapper } = await mountField(XqMarkdownEditorField, node(), 'word');
        const textarea = wrapper.find('textarea').element;

        textarea.setSelectionRange(0, 4);
        await wrapper.find('[data-xq-tool="bold"]').trigger('click');
        await flushPromises();

        expect(store.get('data.body')).toBe('**word**');
        wrapper.unmount();
    });

    it('shows the HTML the server sends for the preview', async () => {
        const fetchMock = vi.fn(() =>
            Promise.resolve(
                new Response(
                    JSON.stringify({
                        $xq: 1,
                        seq: 0,
                        set: {},
                        schema: {},
                        errors: {},
                        result: '<h1>Hi</h1>',
                    }),
                ),
            ),
        );

        vi.stubGlobal('fetch', fetchMock);

        const { wrapper } = await mountField(XqMarkdownEditorField, node(), '# Hi');

        await wrapper.find('[data-xq-markdown-preview]').trigger('click');
        await flushPromises();

        expect(wrapper.find('.xq-rich-content h1').text()).toBe('Hi');
        expect(wrapper.find('textarea').isVisible()).toBe(false);
        wrapper.unmount();
    });

    it('says so when the preview fails, and keeps the text', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.reject(new TypeError('offline'))),
        );

        const { store, wrapper } = await mountField(XqMarkdownEditorField, node(), '# Hi');

        await wrapper.find('[data-xq-markdown-preview]').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Could not load the preview.');
        expect(store.get('data.body')).toBe('# Hi');
        wrapper.unmount();
    });

    it('has no preview without the server', async () => {
        const { wrapper } = await mountField(
            XqMarkdownEditorField,
            field('data.body', 'markdown-editor', { props: { toolbar: [] } }),
        );

        expect(wrapper.find('[data-xq-markdown-preview]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('inserts an attached image by its token', async () => {
        const { store, wrapper } = await mountField(XqMarkdownEditorField, node());

        vi.spyOn(store, 'upload').mockResolvedValue({
            token: 'xq-tmp:abc',
            name: 'a.png',
            size: 1,
            mime: null,
        });

        const input = wrapper.find<HTMLInputElement>('input[type="file"]');

        Object.defineProperty(input.element, 'files', {
            value: [new File(['x'], 'a.png')],
            configurable: true,
        });
        Object.defineProperty(input.element, 'value', { value: '', writable: true });
        await input.trigger('change');
        await flushPromises();

        expect(store.get('data.body')).toContain('![a.png](xq-tmp:abc)');
        wrapper.unmount();
    });
});

describe('rich editor', () => {
    const node = () =>
        field('data.body', 'rich-editor', { props: { toolbar: [['bold', 'h2'], ['undo']] } });

    it('shows the state, and only the buttons it was given', async () => {
        const { wrapper } = await mountField(XqRichEditorField, node(), '<p>Hello</p>');

        expect(wrapper.find('.xq-rich-content').text()).toBe('Hello');
        expect(
            wrapper.findAll('[data-xq-tool]').map((tool) => tool.attributes('data-xq-tool')),
        ).toEqual(['bold', 'h2', 'undo']);
        wrapper.unmount();
    });

    it('writes HTML to the form, and null once it is empty', async () => {
        const { store, wrapper } = await mountField(XqRichEditorField, node(), '<p>Hello</p>');
        const editor = (wrapper.findComponent({ name: 'EditorContent' }).props() as any).editor;

        editor.commands.selectAll();
        editor.commands.toggleBold();
        await flushPromises();

        expect(store.get('data.body')).toBe('<p><strong>Hello</strong></p>');

        editor.commands.clearContent(true);
        await flushPromises();

        expect(store.get('data.body')).toBeNull();
        wrapper.unmount();
    });

    it('follows a value the server sets, without echoing it back', async () => {
        const { store, wrapper } = await mountField(XqRichEditorField, node(), '<p>Old</p>');

        store.set('data.body', '<p>New</p>', 'server');
        await flushPromises();

        expect(wrapper.find('.xq-rich-content').text()).toBe('New');
        expect(store.get('data.body')).toBe('<p>New</p>');
        wrapper.unmount();
    });

    it('keeps the token of an uploaded image in the HTML', async () => {
        const { store, wrapper } = await mountField(XqRichEditorField, node(), null);

        vi.spyOn(store, 'upload').mockResolvedValue({
            token: 'xq-tmp:abc',
            name: 'a.png',
            size: 1,
            mime: null,
        });
        URL.createObjectURL = vi.fn(() => 'blob:local');
        URL.revokeObjectURL = vi.fn();

        const input = wrapper.find<HTMLInputElement>('input[type="file"]');

        Object.defineProperty(input.element, 'files', {
            value: [new File(['x'], 'a.png')],
            configurable: true,
        });
        Object.defineProperty(input.element, 'value', { value: '', writable: true });
        await input.trigger('change');
        await flushPromises();

        expect(store.get('data.body')).toContain('data-xq-upload="xq-tmp:abc"');
        wrapper.unmount();
    });

    it('hides the toolbar and stops editing when disabled', async () => {
        const { wrapper } = await mountField(
            XqRichEditorField,
            field('data.body', 'rich-editor', { disabled: true, props: { toolbar: [['bold']] } }),
            '<p>Hello</p>',
        );

        expect(wrapper.find('[role="toolbar"]').exists()).toBe(false);
        expect(wrapper.find('.xq-rich-content').attributes('contenteditable')).toBe('false');
        wrapper.unmount();
    });
});

describe('rich editor extras', () => {
    const editorOf = (wrapper: any) =>
        (wrapper.findComponent({ name: 'EditorContent' }).props() as any).editor;
    const tools = (wrapper: any) =>
        wrapper.findAll('[data-xq-tool]').map((tool: any) => tool.attributes('data-xq-tool'));

    it('shows the table tools only inside a table', async () => {
        const { wrapper } = await mountField(
            XqRichEditorField,
            field('data.body', 'rich-editor', {
                props: { toolbar: [['table', 'tableAddRowAfter', 'tableDelete']] },
            }),
            '<p>Hi</p><table><tbody><tr><td><p>a</p></td></tr></tbody></table>',
        );
        const editor = editorOf(wrapper);

        expect(tools(wrapper)).toEqual(['table']);

        editor.commands.setTextSelection(editor.state.doc.content.size - 3);
        // The editor tells Vue about a new selection on the next frame.
        await new Promise((resolve) => requestAnimationFrame(resolve));
        await flushPromises();

        expect(tools(wrapper)).toEqual(['table', 'tableAddRowAfter', 'tableDelete']);

        await wrapper.find('[data-xq-tool="tableAddRowAfter"]').trigger('click');
        await flushPromises();

        expect(editor.getHTML().match(/<tr>/g)).toHaveLength(2);
        wrapper.unmount();
    });

    it('keeps JSON instead of HTML when asked', async () => {
        const doc = {
            type: 'doc',
            content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Hi' }] }],
        };
        const { store, wrapper } = await mountField(
            XqRichEditorField,
            field('data.body', 'rich-editor', { props: { toolbar: [['bold']], json: true } }),
            doc as any,
        );
        const editor = editorOf(wrapper);

        editor.commands.selectAll();
        editor.commands.toggleBold();
        await flushPromises();

        const saved = store.get('data.body');

        expect(saved.type).toBe('doc');
        expect(saved.content[0].content).toEqual([
            { type: 'text', text: 'Hi', marks: [{ type: 'bold' }] },
        ]);
        wrapper.unmount();
    });

    it('inserts a merge tag and colors text from its menus', async () => {
        const { store, wrapper } = await mountField(
            XqRichEditorField,
            field('data.body', 'rich-editor', {
                props: {
                    toolbar: [['mergeTags', 'textColor']],
                    mergeTags: { name: 'Full name' },
                    textColors: { '#ef4444': 'Red' },
                },
            }),
            '<p>Hi</p>',
        );

        await wrapper.find('[data-xq-tool="mergeTags"]').trigger('click');
        await flushPromises();
        document.querySelector<HTMLElement>('[data-xq-menu-item="name"]')!.click();
        await flushPromises();

        expect(store.get('data.body')).toContain('{{ name }}');

        editorOf(wrapper).commands.selectAll();
        await wrapper.find('[data-xq-tool="textColor"]').trigger('click');
        await flushPromises();
        document.querySelector<HTMLElement>('[data-xq-menu-item="#ef4444"]')!.click();
        await flushPromises();

        expect(store.get('data.body')).toContain('style="color: #ef4444');
        wrapper.unmount();
    });

    it('keeps the mentions it loads, and offers the items after the character', async () => {
        const { wrapper } = await mountField(
            XqRichEditorField,
            field('data.body', 'rich-editor', {
                props: {
                    toolbar: [['bold']],
                    mentions: [{ char: '@', remote: false, items: [{ value: 1, label: 'Jane' }] }],
                },
            }),
            '<p><span data-type="mention" data-id="1" data-label="Jane" data-mention-suggestion-char="@">@Jane</span> </p>',
        );
        const editor = editorOf(wrapper);

        expect(editor.getHTML()).toContain('data-type="mention"');

        editor.chain().focus('end').insertContent('@Ja').run();
        await new Promise((resolve) => setTimeout(resolve, 10));
        await flushPromises();

        expect(document.querySelector('[data-xq-mentions]')?.textContent).toContain('Jane');
        wrapper.unmount();
    });

    it('draws a block from the preview the server sent', async () => {
        const { wrapper } = await mountField(
            XqRichEditorField,
            field('data.body', 'rich-editor', {
                props: {
                    toolbar: [['customBlocks']],
                    blocks: [{ id: 'hero', label: 'Hero' }],
                    blockPreviews: { 'hero\n{"heading":"Hi"}': '<b>Hi</b>' },
                },
            }),
            '<div data-type="customBlock" data-id="hero" data-config="{&quot;heading&quot;:&quot;Hi&quot;}"></div><div data-type="customBlock" data-id="hero" data-config="{}"></div>',
        );
        const blocks = wrapper.findAll('[data-xq-rich-block]');

        expect(blocks[0].html()).toContain('<b>Hi</b>');
        expect(blocks[1].text()).toBe('Hero');
        wrapper.unmount();
    });
});

describe('an error in an editor', () => {
    it.each([
        ['rich-editor', XqRichEditorField, '.xq-rich-content'],
        ['code-editor', XqCodeEditorField, '.cm-content'],
    ])('focuses the text of a %s, not its toolbar', async (type, component, content) => {
        const props = type === 'rich-editor' ? { toolbar: [['bold']] } : {};
        const { store, wrapper } = await mountField(component, field('data.body', type, { props }));

        await vi.waitFor(() => expect(wrapper.find(content).exists()).toBe(true));

        // A spy, because happy-dom fires selectionchange at once, in the middle of an editor's update.
        const focus = vi
            .spyOn(wrapper.find<HTMLElement>(content).element, 'focus')
            .mockImplementation(() => {});

        store.root.value = wrapper.element.parentElement;
        await store.focusPath('data.body');
        await flushPromises();

        expect(focus).toHaveBeenCalledOnce();
        wrapper.unmount();
    });
});

describe('code editor', () => {
    it('shows the state and writes changes back', async () => {
        const { store, wrapper } = await mountField(
            XqCodeEditorField,
            field('data.body', 'code-editor', { props: {} }),
            'let a = 1;',
        );

        await vi.waitFor(() => expect(wrapper.find('.cm-content').exists()).toBe(true));

        expect(wrapper.find('.cm-content').text()).toContain('let a = 1;');

        store.set('data.body', 'let b = 2;', 'server');
        await flushPromises();

        expect(wrapper.find('.cm-content').text()).toContain('let b = 2;');
        wrapper.unmount();
    });

    it('loads the language it was given', async () => {
        const { wrapper } = await mountField(
            XqCodeEditorField,
            field('data.body', 'code-editor', { props: { language: 'json' } }),
            '{}',
        );

        await vi.waitFor(() => expect(wrapper.find('.cm-content').exists()).toBe(true));
        wrapper.unmount();
    });
});
