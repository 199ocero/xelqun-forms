import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
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
