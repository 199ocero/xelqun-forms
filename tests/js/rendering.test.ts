import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import type { SchemaNode } from '../../resources/js/schema/types';
import { field, orderSchema, schema } from './fixtures';

describe('XqForm rendering', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('renders fields and binds input to state', async () => {
        const store = createFormStore(schema([field('data.name')], { name: 'Ada' }, null));
        const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });
        const input = wrapper.find('input');

        expect(input.element.value).toBe('Ada');

        await input.setValue('Grace');

        expect(store.get('data.name')).toBe('Grace');
        wrapper.unmount();
    });

    describe('conditional text', () => {
        const when = { op: 'eq', path: 'data.is_gift', value: true } as const;
        const text = { when, text: 'Gift', otherwise: 'Plain' };

        function setup(rules: NonNullable<SchemaNode['rules']>, type = 'text-input') {
            const send = vi.fn();
            const store = createFormStore(
                schema(
                    [
                        field('data.is_gift', 'toggle'),
                        field('data.notes', type, { placeholder: 'Plain', rules }),
                    ],
                    { is_gift: false, notes: null },
                ),
                { send },
            );
            const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

            return { store, send, wrapper };
        }

        it('swaps a placeholder in the browser without an update request', async () => {
            const { store, send, wrapper } = setup({ text: { placeholder: text } });
            const input = wrapper.find('[data-xq-path="data.notes"] input');

            expect(input.attributes('placeholder')).toBe('Plain');

            store.set('data.is_gift', true);
            await vi.runAllTimersAsync();

            expect(input.attributes('placeholder')).toBe('Gift');
            expect(send).not.toHaveBeenCalled();
            wrapper.unmount();
        });

        it('swaps a label', async () => {
            const { store, wrapper } = setup({ text: { label: text } });
            const label = wrapper.find('[data-xq-path="data.notes"] label');

            expect(label.text()).toBe('Plain');

            store.set('data.is_gift', true);
            await nextTick();

            expect(label.text()).toBe('Gift');
            wrapper.unmount();
        });

        it.each(['text-input', 'toggle'])(
            'links a %s to a hint that only exists in one branch',
            async (type) => {
                const { store, wrapper } = setup(
                    { text: { hint: { when, text: 'Gift', otherwise: null } } },
                    type,
                );
                const describedBy = () =>
                    wrapper.find('[data-xq-path="data.notes"] [aria-describedby]').exists();

                expect(describedBy()).toBe(false);

                store.set('data.is_gift', true);
                await nextTick();

                expect(describedBy()).toBe(true);
                expect(wrapper.find('[data-xq-path="data.notes"]').text()).toContain('Gift');
                wrapper.unmount();
            },
        );

        it('reads the condition from its own repeater row', async () => {
            const template = [
                field('data.items.{row}.is_gift', 'toggle'),
                field('data.items.{row}.notes', 'text-input', {
                    rules: {
                        text: {
                            placeholder: {
                                when: { op: 'eq', path: 'data.items.{row}.is_gift', value: true },
                                text: 'Gift',
                                otherwise: 'Plain',
                            },
                        },
                    },
                }),
            ];
            const store = createFormStore(
                schema(
                    [field('data.items', 'repeater', { props: { template } })],
                    { items: { a: { is_gift: true }, b: { is_gift: false } } },
                    null,
                ),
            );
            const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

            await nextTick();

            const placeholder = (row: string) =>
                wrapper
                    .find(`[data-xq-path="data.items.${row}.notes"] input`)
                    .attributes('placeholder');

            expect(placeholder('a')).toBe('Gift');
            expect(placeholder('b')).toBe('Plain');
            wrapper.unmount();
        });
    });

    it('hides fields whose conditions fail without a server call', async () => {
        const store = createFormStore(orderSchema());
        const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

        expect(wrapper.find('[data-xq-key="data.company"]').exists()).toBe(false);

        store.set('data.type', 'business');
        await nextTick();

        expect(wrapper.find('[data-xq-key="data.company"]').exists()).toBe(true);
        wrapper.unmount();
    });

    it('keeps the focused input mounted when a patch arrives', async () => {
        const store = createFormStore(
            schema(
                [field('data.name', 'text-input', { deps: ['data.other'] }), field('data.other')],
                { name: 'Ada', other: null },
            ),
        );
        const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });
        const input = wrapper.find<HTMLInputElement>('[data-xq-key="data.name"] input');

        input.element.focus();
        input.element.setSelectionRange(1, 2);

        store.applyPatch(
            {
                $xq: 1,
                seq: 1,
                set: {},
                schema: { 'data.name': { label: 'Full name', placeholder: 'Your name' } },
                errors: {},
            },
            new Map(),
        );
        await nextTick();

        const after = wrapper.find<HTMLInputElement>('[data-xq-key="data.name"] input');

        expect(after.element).toBe(input.element);
        expect(document.activeElement).toBe(input.element);
        expect(after.element.selectionStart).toBe(1);
        expect(after.element.placeholder).toBe('Your name');
        expect(wrapper.text()).toContain('Full name');
        wrapper.unmount();
    });

    it('shows an error box for unknown component types', () => {
        const store = createFormStore(schema([field('data.rating', 'star-rating')], {}, null));
        const wrapper = mount(XqForm, { props: { form: store } });

        expect(wrapper.text()).toContain('No component is registered for type star-rating');
    });
});
