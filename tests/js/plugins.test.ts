import { flushPromises, mount } from '@vue/test-utils';
import { fieldNode, formSchema } from '@xelqun/forms/testing';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { reviewsPlugin } from '../Fixtures/ExamplePlugin/resources/js/index';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createRegistry } from '../../resources/js/components/defaults';
import { eachNode } from '../../resources/js/core/patch';
import { createFormStore } from '../../resources/js/core/store';
import { createXelqunForms, registerPlugins } from '../../resources/js/plugin';
import type { XelqunPlugin } from '../../resources/js/plugin';
import { resolveIcon } from '../../resources/js/ui/icons';
import type { FormStore } from '../../resources/js/core/store';
import type { SchemaNode } from '../../resources/js/schema/types';

const marker = (text: string) => defineComponent({ render: () => h('div', text) });

function render(options: Parameters<typeof createXelqunForms>[0], type: string) {
    return mount(XqForm, {
        attachTo: document.body,
        props: { form: formSchema([fieldNode('data.a', type)], { a: 1 }) },
        global: { plugins: [createXelqunForms(options)] },
    });
}

afterEach(() => {
    vi.restoreAllMocks();
    document.body.innerHTML = '';
});

describe('createXelqunForms', () => {
    it('registers icons from the app and from plugins', () => {
        const fromApp = marker('app');
        const fromPlugin = marker('plugin');

        render(
            {
                icons: { 'app-icon': fromApp },
                plugins: [{ id: 'acme/icons', icons: { 'plugin-icon': fromPlugin } }],
            },
            'text-input',
        ).unmount();

        expect(resolveIcon('app-icon')).toBe(fromApp);
        expect(resolveIcon('plugin-icon')).toBe(fromPlugin);
        expect(resolveIcon('mail')).not.toBeNull();
    });

    it('renders a type from a plugin', () => {
        const plugin: XelqunPlugin = {
            id: 'acme/fields',
            components: { 'acme/rating': marker('stars') },
        };

        expect(render({ plugins: [plugin] }, 'acme/rating').text()).toContain('stars');
    });

    it('lazy-loads a type from a plugin', async () => {
        const plugin: XelqunPlugin = {
            id: 'acme/fields',
            components: { 'acme/rating': () => Promise.resolve(marker('lazy')) },
        };
        const wrapper = render({ plugins: [plugin] }, 'acme/rating');

        await flushPromises();
        await vi.dynamicImportSettled();
        await flushPromises();

        expect(wrapper.text()).toContain('lazy');
    });

    it('runs setup once with the app', () => {
        const setup = vi.fn();

        render({ plugins: [{ id: 'acme/first', setup }] }, 'text-input');

        expect(setup).toHaveBeenCalledTimes(1);
        expect(setup.mock.calls[0][0].app.version).toBeTypeOf('string');
    });

    it('does not run setup when a later plugin fails to register', () => {
        const setup = vi.fn();
        const plugins: XelqunPlugin[] = [
            { id: 'acme/first', setup },
            { id: 'acme/second', components: { rating: marker('x') } },
        ];

        expect(() => render({ plugins }, 'text-input')).toThrow(/vendor prefix/);
        expect(setup).not.toHaveBeenCalled();
    });

    it('gives setup no way around the registry rules', () => {
        const setup = vi.fn();

        render({ plugins: [{ id: 'acme/first', setup }] }, 'text-input');

        expect(setup.mock.calls[0][0]).not.toHaveProperty('registry');
    });

    it('lets the app override a plugin and a built-in without a flag', () => {
        const plugin: XelqunPlugin = { id: 'acme/x', components: { 'acme/a': marker('plugin') } };
        const options = {
            plugins: [plugin],
            components: { 'acme/a': marker('app'), select: marker('app select') },
        };

        expect(render(options, 'acme/a').text()).toContain('app');
        expect(render(options, 'select').text()).toContain('app select');
    });

    it('keeps working without plugins', () => {
        expect(() =>
            createXelqunForms().install!({ provide: vi.fn(), onUnmount: vi.fn() } as never),
        ).not.toThrow();
    });
});

describe('registerPlugins', () => {
    const rating = marker('a');
    const other = marker('b');

    it.each([
        ['an unprefixed type', [{ id: 'p', components: { rating } }], /vendor prefix/],
        ['a built-in type', [{ id: 'p', components: { select: rating } }], /vendor prefix/],
        [
            'two plugins on one type',
            [
                { id: 'acme/one', components: { 'acme/rating': rating } },
                { id: 'other/two', components: { 'acme/rating': other } },
            ],
            /"acme\/one" and "other\/two" both register the type "acme\/rating"/,
        ],
        ['one plugin twice', [{ id: 'p' }, { id: 'p' }], /registered twice/],
        [
            'a new unprefixed type in replaces',
            [{ id: 'p', components: { rating }, replaces: ['rating'] }],
            /vendor prefix/,
        ],
        [
            'two plugins replacing one built-in',
            [
                { id: 'acme/one', components: { select: rating }, replaces: ['select'] },
                { id: 'acme/two', components: { select: other }, replaces: ['select'] },
            ],
            /"acme\/one" and "acme\/two" both register the type "select"/,
        ],
        [
            'a plugin replacing another plugin',
            [
                { id: 'acme/one', components: { 'acme/rating': rating } },
                {
                    id: 'acme/two',
                    components: { 'acme/rating': other },
                    replaces: ['acme/rating'],
                },
            ],
            /"acme\/one" and "acme\/two" both register the type "acme\/rating"/,
        ],
    ])('throws in development for %s', (_name, plugins, message) => {
        expect(() => registerPlugins(createRegistry(), plugins as XelqunPlugin[], true)).toThrow(
            message,
        );
    });

    it('logs in production and keeps the type registered first', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {});
        const registry = createRegistry();

        registerPlugins(
            registry,
            [
                { id: 'acme/one', components: { 'acme/rating': rating } },
                { id: 'other/two', components: { 'acme/rating': other, select: other } },
            ],
            false,
        );

        expect(registry.get('acme/rating')).toBe(rating);
        expect(registry.get('select')).not.toBe(other);
        expect(error).toHaveBeenCalledTimes(2);
    });

    it('replaces a built-in only with an explicit flag', () => {
        const registry = createRegistry();

        registerPlugins(
            registry,
            [{ id: 'acme/x', components: { select: rating }, replaces: ['select'] }],
            true,
        );

        expect(registry.get('select')).toBe(rating);
    });
});

describe('fields with rows', () => {
    const template = [fieldNode('data.steps.{row}.title')];
    const rows = (key: string) => ({
        [key]: [fieldNode(`data.steps.${key}.title`)],
    });
    const checklist = (containsRows: boolean) =>
        formSchema(
            [
                fieldNode('data.steps', 'acme/checklist', {
                    containsRows,
                    props: { template, rowPlaceholder: '{row}', rows: rows('a'), newRow: {} },
                }),
            ],
            { steps: { a: { title: 'One' } } },
        );

    it('indexes rows by the capability flag, whatever the type is called', () => {
        const store = createFormStore(checklist(true));

        expect(store.nodes.has('data.steps.a.title')).toBe(true);

        store.addRow(store.nodes.get('data.steps')!);

        expect(store.rowKeys(store.nodes.get('data.steps')!)).toHaveLength(2);
        expect(store.nodes.size).toBe(3);
    });

    it('builds rows that arrive in a server patch', async () => {
        const store = createFormStore(checklist(true));

        store.applyPatch(
            {
                $xq: 1,
                seq: 1,
                set: { 'data.steps': { a: { title: 'One' }, b: { title: 'Two' } } },
                schema: {},
                errors: {},
            },
            new Map(),
        );
        await nextTick();

        expect(store.nodes.has('data.steps.b.title')).toBe(true);
    });

    it('ignores rows without the flag', () => {
        const visited: string[] = [];

        eachNode(checklist(false).components, (node) => visited.push(node.key));

        expect(visited).toEqual(['data.steps']);
    });
});

describe('the example plugin', () => {
    async function mountExample(form: SchemaNode[] | FormStore, data = {}) {
        const wrapper = mount(XqForm, {
            attachTo: document.body,
            props: { form: Array.isArray(form) ? formSchema(form, data) : form },
            global: { plugins: [createXelqunForms({ plugins: [reviewsPlugin()] })] },
        });

        await vi.dynamicImportSettled();
        await flushPromises();

        return wrapper;
    }

    it('writes a rating to the form state', async () => {
        const wrapper = await mountExample(
            [fieldNode('data.stars', 'acme/rating', { props: { max: 3 } })],
            { stars: null },
        );

        await wrapper.get('[data-star="2"]').trigger('click');

        expect(wrapper.findAll('[data-star]')).toHaveLength(3);
        expect(wrapper.get('[data-star="2"]').attributes('aria-pressed')).toBe('true');
    });

    it('searches through the store', async () => {
        const fetchMock = vi.fn(() =>
            Promise.resolve(
                new Response(JSON.stringify({ options: [{ value: 'ada', label: 'Ada' }] }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                }),
            ),
        );
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = await mountExample([fieldNode('data.person', 'acme/reviewer')], {
            person: null,
        });

        await wrapper.get('input').setValue('ad');
        await flushPromises();
        await wrapper.get('li button').trigger('click');

        expect(wrapper.get('[data-reviewer-selected]').text()).toBe('Ada');
        expect(fetchMock.mock.calls[0]).toContainEqual('/_xelqun/forms/search');

        vi.unstubAllGlobals();
    });

    it('shows the caption the server sends', async () => {
        const wrapper = await mountExample(
            [fieldNode('data.receipt', 'acme/receipt', { props: { caption: 'Add a receipt.' } })],
            { receipt: null },
        );

        expect(wrapper.text()).toContain('Add a receipt.');
    });

    it.each(['acme/rating', 'acme/reviewer', 'acme/receipt'])(
        'shows a server error on %s like a built-in field',
        async (type) => {
            const store = createFormStore(formSchema([fieldNode('data.a', type)], { a: null }));
            const wrapper = await mountExample(store);

            store.setErrors({ 'data.a': 'That will not do.' });
            await nextTick();

            expect(wrapper.text()).toContain('That will not do.');
        },
    );

    it.each(['acme/reviewer', 'acme/receipt'])(
        'ties the error on %s to its input',
        async (type) => {
            const store = createFormStore(formSchema([fieldNode('data.a', type)], { a: null }));
            const wrapper = await mountExample(store);
            const input = wrapper.get('input');

            expect(input.attributes('aria-invalid')).toBeUndefined();

            store.setErrors({ 'data.a': 'That will not do.' });
            await nextTick();

            expect(input.attributes('aria-invalid')).toBe('true');
            expect(wrapper.find(`#${input.attributes('aria-describedby')}`).text()).toBe(
                'That will not do.',
            );
        },
    );
});
