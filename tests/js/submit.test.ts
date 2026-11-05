import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import { field, schema } from './fixtures';

const visit = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({ router: { visit } }));

function mountForm() {
    const store = createFormStore(
        schema([field('data.name'), field('data.email')], { name: null, email: null }, null),
    );
    const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

    return { store, wrapper };
}

describe('failed submit', () => {
    afterEach(() => visit.mockReset());

    it('focuses the first invalid field once it is enabled again', async () => {
        const { store, wrapper } = mountForm();
        const input = wrapper.find<HTMLInputElement>('[data-xq-path="data.email"] input').element;
        let disabledWhenFocused: boolean | null = null;

        vi.spyOn(input, 'focus').mockImplementation(() => {
            disabledWhenFocused = input.disabled;
        });
        visit.mockImplementation(async (_url, options) => {
            options.onError({ 'data.email': 'Invalid email.' });
            await flushPromises();
            options.onFinish();
        });

        await store.submit('/orders');
        await flushPromises();
        await flushPromises();

        expect(disabledWhenFocused).toBe(false);
        wrapper.unmount();
    });

    it('leaves focus alone after a successful submit', async () => {
        const { store, wrapper } = mountForm();
        const focusFirstError = vi.spyOn(wrapper.find<HTMLInputElement>('input').element, 'focus');

        visit.mockImplementation((_url, options) => {
            options.onSuccess({});
            options.onFinish();
        });

        await store.submit('/orders');
        await flushPromises();

        expect(focusFirstError).not.toHaveBeenCalled();
        wrapper.unmount();
    });
});
