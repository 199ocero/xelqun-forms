import { flushPromises, mount } from '@vue/test-utils';
import { fieldNode, formSchema } from '@xelqun/forms/testing';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import { createXelqunForms } from '../../resources/js/plugin';
import Field from '../../stubs/field.vue';
import SearchField from '../../stubs/search-field.vue';
import UploadField from '../../stubs/upload-field.vue';

function render(component: unknown, data: Record<string, unknown> = {}, props = {}) {
    return mount(XqForm, {
        attachTo: document.body,
        props: { form: formSchema([fieldNode('data.a', 'app/a', { props })], data) },
        global: { plugins: [createXelqunForms({ components: { 'app/a': component as never } })] },
    });
}

afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('make:xelqun-field stubs', () => {
    it('binds a basic field to the form state', async () => {
        const wrapper = render(Field, { a: 'One' });
        const input = wrapper.get('input');

        expect((input.element as HTMLInputElement).value).toBe('One');

        await input.setValue('Two');

        expect((input.element as HTMLInputElement).value).toBe('Two');
    });

    it('searches and picks a result', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() =>
                Promise.resolve(
                    new Response(JSON.stringify({ options: [{ value: 'ada', label: 'Ada' }] }), {
                        status: 200,
                        headers: { 'Content-Type': 'application/json' },
                    }),
                ),
            ),
        );

        const wrapper = render(SearchField, { a: null });

        await wrapper.get('input').setValue('ad');
        await flushPromises();
        await wrapper.get('li button').trigger('click');

        expect(wrapper.text()).toContain('Ada');
    });

    it.each([
        ['field', Field],
        ['search field', SearchField],
        ['upload field', UploadField],
    ])('ties the error of the %s to its input', async (_name, component) => {
        const store = createFormStore(formSchema([fieldNode('data.a', 'app/a')], { a: null }));
        const wrapper = mount(XqForm, {
            attachTo: document.body,
            props: { form: store },
            global: {
                plugins: [createXelqunForms({ components: { 'app/a': component as never } })],
            },
        });
        const input = wrapper.get('input');

        expect(input.attributes('aria-invalid')).toBeUndefined();

        store.setErrors({ 'data.a': 'That will not do.' });
        await nextTick();

        expect(input.attributes('aria-invalid')).toBe('true');
        expect(wrapper.find(`#${input.attributes('aria-describedby')}`).text()).toBe(
            'That will not do.',
        );
    });

    it('offers a file input with the accepted types', () => {
        const wrapper = render(UploadField, { a: 'photos/desk.png' }, { accept: 'image/*' });

        expect(wrapper.get('input[type=file]').attributes('accept')).toBe('image/*');
        expect(wrapper.text()).toContain('desk.png');
    });
});
