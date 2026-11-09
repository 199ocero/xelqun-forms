import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import type { FieldSlot, SchemaNode } from '../../resources/js/schema/types';
import { field, schema } from './fixtures';

function display(
    type: string,
    key: string,
    props: Record<string, unknown>,
    extra = {},
): SchemaNode {
    return { key, type, hidden: false, path: null, props, children: [], ...extra };
}

function text(content: string, slot?: FieldSlot): SchemaNode {
    return display('text', `t-${content}`, { content }, slot ? { slot } : {});
}

function render(components: SchemaNode[], data = {}, messages?: Record<string, string>) {
    const store = createFormStore({ ...schema(components, data, null), messages });

    return { store, wrapper: mount(XqForm, { props: { form: store }, attachTo: document.body }) };
}

describe('field slots', () => {
    const slots: FieldSlot[] = [
        'aboveLabel',
        'beforeLabel',
        'afterLabel',
        'belowLabel',
        'aboveContent',
        'beforeContent',
        'afterContent',
        'belowContent',
        'aboveErrorMessage',
        'belowErrorMessage',
    ];

    it.each(['text-input', 'checkbox', 'toggle'])('renders every slot of a %s', (type) => {
        const { wrapper } = render([
            field('data.name', type, { children: slots.map((slot) => text(slot, slot)) }),
        ]);

        for (const slot of slots) {
            expect(wrapper.text()).toContain(slot);
        }

        wrapper.unmount();
    });

    it('puts the error between its two slots, and each slot where it belongs', async () => {
        const { store, wrapper } = render([
            field('data.name', 'text-input', { children: slots.map((slot) => text(slot, slot)) }),
        ]);

        store.setErrors({ 'data.name': 'Nope' });
        await wrapper.vm.$nextTick();

        const found = wrapper.text();
        const order = [
            'aboveLabel',
            'beforeLabel',
            'name',
            'afterLabel',
            'belowLabel',
            'aboveContent',
            'beforeContent',
            'afterContent',
            'belowContent',
            'aboveErrorMessage',
            'Nope',
            'belowErrorMessage',
        ].map((piece) => found.indexOf(piece));

        expect(order).toEqual([...order].sort((a, b) => a - b));
        wrapper.unmount();
    });

    it('hides a slot the server hides, and follows its rules', async () => {
        const { store, wrapper } = render(
            [
                field('data.name', 'text-input', {
                    children: [text('Shown when on', 'aboveLabel')].map((node) => ({
                        ...node,
                        rules: { visible: { op: 'eq', path: 'data.on', value: true } },
                    })) as SchemaNode[],
                }),
            ],
            { on: false },
        );

        expect(wrapper.text()).not.toContain('Shown when on');

        store.set('data.on', true);
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Shown when on');
        wrapper.unmount();
    });
});

describe('slots with rules', () => {
    it('leaves no empty row for a slot that is hidden', () => {
        const { wrapper } = render(
            [
                field('data.a', 'text-input', {
                    children: [
                        {
                            ...text('Hidden', 'belowContent'),
                            rules: { visible: { op: 'eq', path: 'data.on', value: true } },
                        },
                    ],
                }),
            ],
            { on: false },
        );

        expect(wrapper.find('[data-xq-path="data.a"] .flex-wrap').exists()).toBe(false);
        wrapper.unmount();
    });
});

describe('text', () => {
    it('escapes plain content and renders only what PHP marked as HTML', () => {
        const { wrapper } = render([
            display('text', 'plain', { content: '<img src=x onerror=boom>', html: null }),
            display('text', 'html', { content: null, html: '<strong>Bold</strong>' }),
        ]);

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('<img src=x onerror=boom>');
        expect(wrapper.find('strong').text()).toBe('Bold');
        wrapper.unmount();
    });

    it('turns a named color into a class and ignores an unknown one', () => {
        const { wrapper } = render([
            display('text', 'a', { content: 'A', color: 'danger' }),
            display('text', 'b', { content: 'B', color: 'nope' }),
        ]);
        const [first, second] = wrapper.findAll('p').filter((p) => /^[AB]$/.test(p.text()));

        expect(first.classes()).toContain('text-xq-destructive');
        expect(second.classes()).toContain('text-xq-muted-foreground');
        wrapper.unmount();
    });
});

describe('icon', () => {
    it('stays out of the accessibility tree without a name', () => {
        const { wrapper } = render([display('icon', 'a', { icon: 'star' })]);

        expect(wrapper.find('[role="img"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('takes its name from a tooltip or an aria-label', () => {
        const { wrapper } = render([
            display('icon', 'a', { icon: 'star', tooltip: 'Starred' }),
            display(
                'icon',
                'b',
                { icon: 'star' },
                { extraAttributes: { 'aria-label': 'Verified' } },
            ),
        ]);

        expect(
            wrapper.findAll('[role="img"]').map((icon) => icon.attributes('aria-label')),
        ).toEqual(['Starred', 'Verified']);
        wrapper.unmount();
    });
});

describe('image', () => {
    it('treats a number as pixels and a string as CSS', () => {
        const { wrapper } = render([
            display('image', 'a', { url: '/a.png', alt: 'A', width: 40, height: '2rem' }),
        ]);
        const image = wrapper.find('img');

        expect(image.attributes('alt')).toBe('A');
        expect(image.attributes('style')).toContain('width: 40px');
        expect(image.attributes('style')).toContain('height: 2rem');
        wrapper.unmount();
    });
});

describe('flex', () => {
    it('stacks below the breakpoint and marks a child that keeps its width', () => {
        const { wrapper } = render([
            display(
                'flex',
                'f',
                { from: 'md' },
                {
                    children: [field('data.a'), field('data.b', 'text-input', { grow: false })],
                },
            ),
        ]);
        const flex = wrapper.find('.xq-flex');

        expect(flex.classes()).toEqual(expect.arrayContaining(['flex-col', 'md:flex-row']));
        expect(flex.findAll('[data-xq-grow="false"]')).toHaveLength(1);
        wrapper.unmount();
    });

    it('stays a row without a breakpoint', () => {
        const { wrapper } = render([display('flex', 'f', {}, { children: [field('data.a')] })]);

        expect(wrapper.find('.xq-flex').classes()).toContain('flex-row');
        wrapper.unmount();
    });
});

describe('fused group', () => {
    function fused() {
        return render(
            [
                display(
                    'fused-group',
                    'g',
                    { columns: { default: 2 } },
                    {
                        label: 'Location',
                        children: [
                            field('data.city', 'text-input', { required: true, hiddenLabel: true }),
                            field('data.zip', 'text-input', { hiddenLabel: true }),
                        ],
                    },
                ),
            ],
            { city: null, zip: null },
        );
    }

    it('names the group and shows it is required if a field is', () => {
        const { wrapper } = fused();
        const group = wrapper.find('[role="group"]');

        expect(group.attributes('aria-label')).toBe('Location');
        expect(group.find('p').text()).toContain('*');
        wrapper.unmount();
    });

    it('lists errors below the group, under the id each control points at', async () => {
        const { store, wrapper } = fused();

        store.setErrors({ 'data.zip': 'Bad zip' });
        await wrapper.vm.$nextTick();

        const input = wrapper.find<HTMLInputElement>('[data-xq-path="data.zip"] input');
        const message = wrapper.find(`#${input.attributes('aria-describedby')}`);

        expect(message.text()).toBe('Bad zip');
        expect(wrapper.find('.xq-fused').text()).not.toContain('Bad zip');
        wrapper.unmount();
    });
});

describe('fused group errors', () => {
    it.each(['checkbox', 'toggle'])('shows the error of a %s once', async (type) => {
        const { store, wrapper } = render(
            [display('fused-group', 'g', {}, { children: [field('data.a', type)] })],
            { a: false },
        );

        store.setErrors({ 'data.a': 'Bad' });
        await wrapper.vm.$nextTick();

        expect(wrapper.text().match(/Bad/g)).toHaveLength(1);
        wrapper.unmount();
    });
});

describe('callout and empty state', () => {
    it('shows the status icon and its footer', () => {
        const { wrapper } = render([
            display(
                'callout',
                'c',
                { status: 'danger', icon: 'circle-alert', description: 'Why' },
                {
                    label: 'Careful',
                    children: [text('More')],
                },
            ),
        ]);

        expect(wrapper.text()).toContain('Careful');
        expect(wrapper.text()).toContain('More');
        expect(wrapper.find('svg').classes()).toContain('text-xq-destructive');
        wrapper.unmount();
    });

    it('drops the border of an empty state that is not contained', () => {
        const { wrapper } = render([
            display('empty-state', 'a', { contained: true }, { label: 'One' }),
            display('empty-state', 'b', { contained: false }, { label: 'Two' }),
        ]);

        expect(wrapper.findAll('.border-dashed')).toHaveLength(1);
        wrapper.unmount();
    });
});

describe('translations', () => {
    it('translates what the form shows, and leaves the rest in English', () => {
        const { wrapper } = render(
            [field('data.a', 'text-input', { required: true })],
            {},
            {
                '(required)': '(obligatorio)',
                Save: 'Guardar',
            },
        );

        expect(wrapper.text()).toContain('(obligatorio)');
        wrapper.unmount();
    });

    it('keeps a placeholder apart from a longer one that starts the same', () => {
        const { t } = createFormStore(schema([]));

        expect(t('Showing :to of :total', { to: 10, total: 25 })).toBe('Showing 10 of 25');
        expect(t('Showing :to of :total', { total: 25, to: 10 })).toBe('Showing 10 of 25');
        expect(t(':item and :items', { item: 'one', items: 'many' })).toBe('one and many');
    });

    it('leaves a value that looks like a placeholder as it is', () => {
        const { t } = createFormStore(schema([]));

        expect(t('Upload :file for :name', { file: ':name.png', name: 'Ada' })).toBe(
            'Upload :name.png for Ada',
        );
    });

    it('leaves a placeholder it has no value for', () => {
        const { t } = createFormStore(schema([]));

        expect(t('Remove :name at :time', { name: 'a' })).toBe('Remove a at :time');
        expect(t('Remove :constructor')).toBe('Remove :constructor');
    });

    it('fills a placeholder with text right after it, as Laravel does', () => {
        const { t } = createFormStore(schema([]));

        expect(t('Up to :sizeMB', { size: 5 })).toBe('Up to 5MB');
    });

    it('leaves text with a colon alone', () => {
        const { t } = createFormStore(schema([]));

        expect(t('Note: at 12:30')).toBe('Note: at 12:30');
    });

    it('fills in a value of zero', () => {
        const { t } = createFormStore(schema([]));

        expect(t(':count left', { count: 0 })).toBe('0 left');
    });

    it('fills placeholders in a translated line', () => {
        const store = createFormStore({
            ...schema([]),
            messages: { 'Remove :name': 'Quitar :name' },
        });

        expect(store.t('Remove :name', { name: 'foto.png' })).toBe('Quitar foto.png');
        expect(store.t('Move :name up', { name: 'a' })).toBe('Move a up');
        expect(store.t('Move :name up', { name: 'a$&b.png' })).toBe('Move a$&b.png up');
    });
});

describe('section', () => {
    function section(props = {}, children: SchemaNode[] = []): SchemaNode {
        return {
            ...field('', 'section'),
            key: 'data#0',
            path: null,
            label: 'Billing',
            props: { collapsible: true, ...props },
            children: [field('data.card'), ...children],
        };
    }

    it('puts header and footer content outside the fields', () => {
        const { wrapper } = render([
            section({}, [
                { ...text('Paid'), slot: 'afterHeader' },
                { ...text('Due'), slot: 'footer' },
            ]),
        ]);

        expect(wrapper.find('header').text()).toContain('Paid');
        expect(wrapper.find('footer').text()).toBe('Due');
        expect(wrapper.find('.xq-grid .xq-grid').text()).not.toContain('Paid');
        wrapper.unmount();
    });

    it('remembers that it was collapsed', async () => {
        const { wrapper: first } = render([section({ persistCollapsed: true })]);

        await first.find('[aria-expanded]').trigger('click');
        first.unmount();

        const { wrapper } = render([section({ persistCollapsed: true })]);

        await wrapper.vm.$nextTick();
        expect(wrapper.find('[aria-expanded]').attributes('aria-expanded')).toBe('false');
        wrapper.unmount();
        localStorage.clear();
    });
});
