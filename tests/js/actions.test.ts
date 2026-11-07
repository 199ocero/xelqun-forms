import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import XqAction from '../../resources/js/components/XqAction.vue';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import type { SchemaNode } from '../../resources/js/schema/types';
import { field, schema } from './fixtures';

vi.mock('@inertiajs/vue3', () => ({
    router: { visit: vi.fn() },
    Link: { props: ['href'], template: '<a data-inertia-link :href="href"><slot /></a>' },
}));

function action(props: Record<string, unknown> = {}): SchemaNode {
    return {
        key: 'data#1',
        type: 'action',
        hidden: false,
        path: null,
        props: { label: 'Apply', heading: 'Apply it', style: 'button', ...props },
        children: [],
        deps: [],
    };
}

function respond(body: unknown, status = 200) {
    return Promise.resolve(new Response(JSON.stringify(body), { status }));
}

function patch(extra = {}) {
    return { $xq: 1, seq: 0, set: {}, schema: {}, errors: {}, ...extra };
}

const modalSchema = schema([field('data.amount')], { amount: 5 }, 'modal-token');

async function mountAction(node = action()) {
    const store = createFormStore(schema([field('data.note'), node], { note: 'old' }));
    const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

    await flushPromises();

    return { store, wrapper, button: wrapper.find('[data-xq-action] button') };
}

const inModal = () =>
    document.body.querySelector<HTMLElement>('[role="dialog"], [role="alertdialog"]');

function modalButton(label: string): HTMLButtonElement {
    return [...inModal()!.querySelectorAll('button')].find((item) =>
        item.textContent?.includes(label),
    ) as HTMLButtonElement;
}

function submitModal(): void {
    inModal()!
        .querySelector('form')!
        .dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
}

describe('where actions render', () => {
    function row(key: string): SchemaNode[] {
        return [
            field(`data.items.${key}.qty`),
            { ...action({ label: 'Mail' }), key: `data.items.${key}#a0`, slot: 'itemAction' },
        ];
    }

    function repeater(props = {}): SchemaNode {
        return field('data.items', 'repeater', {
            containsRows: true,
            props: {
                template: [],
                rows: { r1: row('r1') },
                itemLabel: 'Item',
                addable: true,
                ...props,
            },
        });
    }

    it('puts item actions in the row header, not in the fields', () => {
        const store = createFormStore(schema([repeater()], { items: { r1: { qty: 1 } } }));
        const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });
        const header = wrapper.find('[data-xq-row] > div');

        expect(header.find('[data-xq-action]').exists()).toBe(true);
        expect(header.text()).toContain('Mail');
        expect(wrapper.findAll('[data-xq-action]')).toHaveLength(1);
        wrapper.unmount();
    });

    it('keeps the table columns lined up', () => {
        const table = [{ label: 'Qty', hiddenLabel: false, required: false, width: null }];
        const store = createFormStore(schema([repeater({ table })], { items: { r1: { qty: 1 } } }));
        const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

        expect(wrapper.findAll('tbody tr td')).toHaveLength(2);
        expect(wrapper.findAll('tbody tr td')[1].text()).toContain('Mail');
        wrapper.unmount();
    });

    it.each(['prefixAction', 'suffixAction', 'optionAction'] as const)(
        'shows %s by the control',
        (slot) => {
            const store = createFormStore(
                schema([field('data.a', 'text-input', { children: [{ ...action(), slot }] })], {
                    a: null,
                }),
            );
            const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

            expect(wrapper.find('[data-xq-path="data.a"] [data-xq-action]').exists()).toBe(true);
            wrapper.unmount();
        },
    );

    it('puts the input and its buttons in one row, in order', () => {
        const store = createFormStore(
            schema(
                [
                    field('data.a', 'text-input', {
                        children: [
                            { ...action({ label: 'After' }), key: 'after', slot: 'suffixAction' },
                            { ...action({ label: 'Before' }), key: 'before', slot: 'prefixAction' },
                        ],
                    }),
                ],
                { a: null },
            ),
        );
        const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });
        const html = wrapper.find('[data-xq-path="data.a"]').html();

        expect(html.indexOf('Before')).toBeLessThan(html.indexOf('<input'));
        expect(html.indexOf('<input')).toBeLessThan(html.indexOf('After'));
        wrapper.unmount();
    });
});

describe('actions', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    const sent = (call = 0) => JSON.parse(fetchMock.mock.calls[call][1].body);

    beforeEach(() => {
        fetchMock = vi.fn(() => respond(patch()));
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.body.innerHTML = '';
    });

    it('runs straight away without a modal, and applies the patch', async () => {
        fetchMock.mockImplementation(() => respond(patch({ set: { 'data.note': 'new' } })));

        const { store, button, wrapper } = await mountAction();

        await button.trigger('click');
        await flushPromises();

        expect(fetchMock.mock.calls[0][0]).toBe('/_xelqun/forms/action');
        expect(sent()).toMatchObject({
            token: 'token',
            action: 'data#1',
            phase: 'run',
            state: { data: { note: 'old' } },
        });
        expect(store.get('data.note')).toBe('new');
        wrapper.unmount();
    });

    it('asks first when it needs a confirmation, and does nothing if cancelled', async () => {
        const { button, wrapper } = await mountAction(action({ confirmation: true }));

        await button.trigger('click');
        await flushPromises();

        expect(fetchMock).not.toHaveBeenCalled();
        expect(inModal()?.textContent).toContain('Apply it');

        modalButton('Cancel').click();
        await flushPromises();

        expect(fetchMock).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('runs once the user confirms', async () => {
        const { button, wrapper } = await mountAction(action({ confirmation: true }));

        await button.trigger('click');
        await flushPromises();
        modalButton('Confirm').click();
        await flushPromises();

        expect(sent().phase).toBe('run');
        wrapper.unmount();
    });

    it('opens a modal from the schema the server sends, with its own store', async () => {
        fetchMock.mockImplementation(() => respond({ form: modalSchema }));

        const { store, button, wrapper } = await mountAction(action({ hasForm: true }));

        await button.trigger('click');
        await flushPromises();

        expect(sent().phase).toBe('mount');
        expect(inModal()!.querySelector('input')!.value).toBe('5');
        expect(store.nodes.has('data.amount')).toBe(false);
        wrapper.unmount();
    });

    it('posts the modal values with the parent state, then closes', async () => {
        fetchMock
            .mockImplementationOnce(() => respond({ form: modalSchema }))
            .mockImplementationOnce(() => respond(patch({ set: { 'data.note': 'done' } })));

        const { store, button, wrapper } = await mountAction(action({ hasForm: true }));

        await button.trigger('click');
        await flushPromises();
        submitModal();
        await flushPromises();

        expect(sent(1)).toMatchObject({
            token: 'token',
            phase: 'run',
            data: { amount: 5 },
            state: { data: { note: 'old' } },
        });
        expect(store.get('data.note')).toBe('done');
        expect(inModal()).toBeNull();
        wrapper.unmount();
    });

    it('stays open and shows the errors the server finds', async () => {
        fetchMock
            .mockImplementationOnce(() => respond({ form: modalSchema }))
            .mockImplementationOnce(() =>
                respond({ errors: { 'data.amount': ['Too much.'] } }, 422),
            );

        const { store, button, wrapper } = await mountAction(action({ hasForm: true }));

        await button.trigger('click');
        await flushPromises();
        submitModal();
        await flushPromises();

        expect(inModal()!.textContent).toContain('Too much.');
        expect(store.errors).toEqual({});
        wrapper.unmount();
    });

    it('shows a server error on an edited field of the modal', async () => {
        fetchMock
            .mockImplementationOnce(() => respond({ form: modalSchema }))
            .mockImplementationOnce(() =>
                respond({ errors: { 'data.amount': ['Too much.'] } }, 422),
            );

        const { button, wrapper } = await mountAction(action({ hasForm: true }));

        await button.trigger('click');
        await flushPromises();

        const input = inModal()!.querySelector('input')!;

        input.value = '9';
        input.dispatchEvent(new Event('input'));
        submitModal();
        await flushPromises();

        expect(inModal()!.textContent).toContain('Too much.');
        wrapper.unmount();
    });

    it('stops in the browser when the modal has rules it can check', async () => {
        const form = schema(
            [
                field('data.amount', 'text-input', {
                    required: true,
                    validation: {
                        attribute: 'Amount',
                        rules: [{ rule: 'required', message: 'The :attribute field is required.' }],
                    },
                }),
            ],
            { amount: null },
        );

        fetchMock.mockImplementationOnce(() => respond({ form }));

        const { button, wrapper } = await mountAction(action({ hasForm: true }));

        await button.trigger('click');
        await flushPromises();
        submitModal();
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledOnce();
        expect(inModal()!.textContent).toContain('The Amount field is required.');
        wrapper.unmount();
    });

    it('puts an error about the parent on the parent', async () => {
        fetchMock.mockImplementation(() =>
            respond({ errors: { 'data.note': ['Not today.'] } }, 422),
        );

        const { store, button, wrapper } = await mountAction();

        store.set('data.note', 'edited');
        await button.trigger('click');
        await flushPromises();

        expect(store.errors).toEqual({ 'data.note': 'Not today.' });
        wrapper.unmount();
    });

    describe('how a modal looks', () => {
        const icon = () => inModal()!.querySelector('[data-xq-dialog-icon]');
        const footer = () => inModal()!.querySelector('[data-xq-dialog-footer]');
        const closeButton = () => inModal()!.querySelector('[aria-label="Close"]');

        async function openForm(props = {}) {
            fetchMock.mockImplementationOnce(() => respond({ form: modalSchema }));

            const mounted = await mountAction(action({ hasForm: true, ...props }));

            await mounted.button.trigger('click');
            await flushPromises();

            return mounted;
        }

        async function openConfirmation(props = {}) {
            const mounted = await mountAction(action({ confirmation: true, ...props }));

            await mounted.button.trigger('click');
            await flushPromises();

            return mounted;
        }

        it('starts plain, with a close button and the buttons at the end', async () => {
            const { wrapper } = await openForm();

            expect(icon()).toBeNull();
            expect(closeButton()).not.toBeNull();
            expect(footer()!.className).toContain('justify-end');
            expect(inModal()!.hasAttribute('aria-describedby')).toBe(false);
            wrapper.unmount();
        });

        it('takes an icon, its color and the alignments from the server', async () => {
            const { wrapper } = await openForm({
                description: 'Why',
                modalIcon: 'heart',
                modalIconColor: 'success',
                alignment: 'center',
                footerAlignment: 'full',
            });

            expect(icon()!.className).toContain('text-xq-success');
            expect(icon()!.parentElement!.className).toContain('text-center');
            expect(footer()!.className).toContain('*:flex-1');
            expect(inModal()!.hasAttribute('aria-describedby')).toBe(true);
            wrapper.unmount();
        });

        it('leaves the close button out when asked', async () => {
            const { wrapper } = await openForm({ closeButton: false });

            expect(closeButton()).toBeNull();
            wrapper.unmount();
        });

        it('falls back to the primary tint for a color it does not know', async () => {
            const { wrapper } = await openForm({ modalIcon: 'heart', modalIconColor: 'teal' });

            expect(icon()!.className).toContain('text-xq-primary');
            wrapper.unmount();
        });

        it('warns with a centered icon in the color of a confirmation', async () => {
            const { wrapper } = await openConfirmation({ color: 'danger' });

            expect(icon()!.className).toContain('text-xq-destructive');
            expect(icon()!.parentElement!.className).toContain('text-center');
            expect(footer()!.className).toContain('*:flex-1');
            wrapper.unmount();
        });

        it('lets a confirmation choose its own look', async () => {
            const { wrapper } = await openConfirmation({
                modalIcon: 'heart',
                modalIconColor: 'info',
                alignment: 'start',
                footerAlignment: 'end',
            });

            expect(icon()!.className).toContain('text-xq-info');
            expect(icon()!.parentElement!.className).not.toContain('text-center');
            expect(footer()!.className).toContain('justify-end');
            wrapper.unmount();
        });
    });

    describe('while it runs', () => {
        const spinner = '.motion-safe\\:animate-spin';

        beforeEach(() => vi.useFakeTimers());
        afterEach(() => vi.useRealTimers());

        it('changes nothing on screen for a quick action', async () => {
            fetchMock.mockImplementation(() => new Promise(() => {}));

            const { button, wrapper } = await mountAction();

            await button.trigger('click');
            await vi.advanceTimersByTimeAsync(199);

            expect(button.attributes('disabled')).toBeUndefined();
            expect(button.attributes('aria-busy')).toBe('true');
            expect(button.find(spinner).exists()).toBe(false);
            wrapper.unmount();
        });

        it('shows a spinner over the label after a noticeable wait, keeping the width', async () => {
            fetchMock.mockImplementation(() => new Promise(() => {}));

            const { button, wrapper } = await mountAction();

            await button.trigger('click');
            await vi.advanceTimersByTimeAsync(200);

            expect(button.attributes('disabled')).toBeUndefined();
            expect(button.find(spinner).exists()).toBe(true);
            expect(button.find('span span').classes()).toContain('opacity-0');
            wrapper.unmount();
        });

        it('draws a check when it finishes, then goes back to its label', async () => {
            fetchMock.mockImplementationOnce(() => respond(patch()));
            const { button, wrapper } = await mountAction();

            await button.trigger('click');
            await vi.advanceTimersByTimeAsync(50);

            expect(button.find('[data-xq-action-done]').exists()).toBe(true);

            await vi.advanceTimersByTimeAsync(1000);

            expect(button.find('[data-xq-action-done]').exists()).toBe(false);
            wrapper.unmount();
        });

        it('shakes when it fails, and shows no check', async () => {
            fetchMock.mockImplementationOnce(() => respond({ message: 'Nope' }, 500));
            const { button, wrapper } = await mountAction();

            await button.trigger('click');
            await vi.advanceTimersByTimeAsync(50);

            expect(button.classes()).toContain('animate-xq-shake');
            expect(button.find('[data-xq-action-done]').exists()).toBe(false);

            await vi.advanceTimersByTimeAsync(1000);

            expect(button.classes()).not.toContain('animate-xq-shake');
            wrapper.unmount();
        });

        it('leaves the button alone when a modal refuses, as the modal says why', async () => {
            fetchMock.mockImplementationOnce(() => respond({ form: modalSchema }));
            fetchMock.mockImplementationOnce(() =>
                respond({ message: 'Nope', errors: { 'data.amount': ['Bad'] } }, 422),
            );
            const { button, wrapper } = await mountAction(action({ hasForm: true }));

            await button.trigger('click');
            await vi.advanceTimersByTimeAsync(50);
            submitModal();
            await vi.advanceTimersByTimeAsync(50);

            expect(button.classes()).not.toContain('animate-xq-shake');
            wrapper.unmount();
        });

        it('can be unmounted while it shows its outcome', async () => {
            fetchMock.mockImplementationOnce(() => respond(patch()));
            const { button, wrapper } = await mountAction();

            await button.trigger('click');
            await vi.advanceTimersByTimeAsync(50);
            wrapper.unmount();

            await vi.advanceTimersByTimeAsync(2000);

            expect(wrapper.exists()).toBe(false);
        });

        it('sends a modal once, however often it is submitted', async () => {
            fetchMock.mockImplementationOnce(() => respond({ form: modalSchema }));

            const { button, wrapper } = await mountAction(action({ hasForm: true }));

            await button.trigger('click');
            await vi.advanceTimersByTimeAsync(10);
            fetchMock.mockClear();
            fetchMock.mockImplementation(() => new Promise(() => {}));

            submitModal();
            submitModal();
            await vi.advanceTimersByTimeAsync(10);

            expect(fetchMock).toHaveBeenCalledOnce();
            expect(modalButton('Save').disabled).toBe(false);
            expect(modalButton('Save').getAttribute('aria-busy')).toBe('true');
            wrapper.unmount();
        });

        it('ignores a second click', async () => {
            fetchMock.mockImplementation(() => new Promise(() => {}));

            const { button, wrapper } = await mountAction();

            await button.trigger('click');
            await button.trigger('click');
            await vi.advanceTimersByTimeAsync(10);

            expect(fetchMock).toHaveBeenCalledOnce();
            wrapper.unmount();
        });
    });

    it('says so when the request fails, and frees the button', async () => {
        fetchMock.mockImplementation(() => Promise.reject(new TypeError('offline')));

        const { store, button, wrapper } = await mountAction();

        await button.trigger('click');
        await flushPromises();

        expect(store.liveError.value).toContain('Check your connection');
        expect(button.attributes('disabled')).toBeUndefined();
        wrapper.unmount();
    });

    it('shows a failed run inside the modal the user is looking at', async () => {
        fetchMock
            .mockImplementationOnce(() => respond({ form: modalSchema }))
            .mockImplementationOnce(() => Promise.reject(new TypeError('offline')));

        const { store, button, wrapper } = await mountAction(action({ hasForm: true }));

        await button.trigger('click');
        await flushPromises();
        submitModal();
        await flushPromises();

        expect(inModal()!.textContent).toContain('Check your connection');
        expect(store.liveError.value).toBeNull();
        wrapper.unmount();
    });

    it('takes the failure message away when a retry works', async () => {
        fetchMock
            .mockImplementationOnce(() => Promise.reject(new TypeError('offline')))
            .mockImplementationOnce(() => respond(patch()));

        const { store, button, wrapper } = await mountAction();

        await button.trigger('click');
        await flushPromises();
        await button.trigger('click');
        await flushPromises();

        expect(store.liveError.value).toBeNull();
        wrapper.unmount();
    });

    it('runs once when the modal is submitted twice', async () => {
        fetchMock
            .mockImplementationOnce(() => respond({ form: modalSchema }))
            .mockImplementation(() => respond(patch()));

        const { button, wrapper } = await mountAction(action({ hasForm: true }));

        await button.trigger('click');
        await flushPromises();
        submitModal();
        submitModal();
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        wrapper.unmount();
    });

    it('shows modal errors even when it opened from a fused group', async () => {
        fetchMock
            .mockImplementationOnce(() => respond({ form: modalSchema }))
            .mockImplementationOnce(() =>
                respond({ errors: { 'data.amount': ['Too much.'] } }, 422),
            );

        const fused = {
            key: 'g',
            type: 'fused-group',
            hidden: false,
            path: null,
            props: {},
            children: [action({ hasForm: true })],
        } as SchemaNode;
        const { button, wrapper } = await mountAction(fused);

        await button.trigger('click');
        await flushPromises();
        submitModal();
        await flushPromises();

        expect(inModal()!.textContent).toContain('Too much.');
        wrapper.unmount();
    });

    it('shows a new option straight away', async () => {
        const select = field('data.country', 'select', {
            props: { options: [], searchable: false, multiple: false },
            children: [{ ...action(), slot: 'optionAction' }],
        });

        fetchMock.mockImplementation(() =>
            respond(
                patch({
                    set: { 'data.country': 7 },
                    schema: {
                        'data.country': { props: { options: [{ value: 7, label: 'Norway' }] } },
                    },
                }),
            ),
        );

        const store = createFormStore(schema([select], { country: null }));
        const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

        await wrapper.find('[data-xq-action] button').trigger('click');
        await flushPromises();

        expect(store.get('data.country')).toBe(7);
        expect(wrapper.text()).toContain('Norway');
        wrapper.unmount();
    });

    it('opens nothing when the server refuses', async () => {
        fetchMock.mockImplementation(() => respond({ message: 'No' }, 403));

        const { store, button, wrapper } = await mountAction(action({ hasForm: true }));

        await button.trigger('click');
        await flushPromises();

        expect(inModal()).toBeNull();
        expect(store.liveError.value).not.toBeNull();
        wrapper.unmount();
    });

    it('waits for live updates, so the action sees what the user typed', async () => {
        const { store, button, wrapper } = await mountAction();
        const idle = vi.spyOn(store.queue, 'idle');

        await button.trigger('click');
        await flushPromises();

        expect(idle).toHaveBeenCalled();
        wrapper.unmount();
    });

    it('disables the button when the server says so', async () => {
        const { button, wrapper } = await mountAction(action({ disabled: true }));

        expect(button.attributes('disabled')).toBeDefined();
        wrapper.unmount();
    });

    it('keeps the name of an icon button for screen readers', async () => {
        const { button, wrapper } = await mountAction(action({ style: 'icon', icon: 'star' }));

        expect(button.attributes('aria-label')).toBe('Apply');
        expect(button.text()).toBe('');
        wrapper.unmount();
    });

    it('shows nothing for an action the server hides', async () => {
        const { wrapper } = await mountAction({ ...action(), hidden: true });

        expect(wrapper.find('[data-xq-action]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('goes to a url without calling the server', async () => {
        const { wrapper } = await mountAction(action({ url: '/guide' }));
        const link = wrapper.find('[data-xq-action] a');

        expect(link.attributes('href')).toBe('/guide');
        expect(link.attributes('data-inertia-link')).toBeDefined();
        expect(fetch).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('uses a plain link for another site and a new tab', async () => {
        const external = await mountAction(action({ url: 'https://example.com/a' }));

        expect(external.wrapper.find('a').attributes('data-inertia-link')).toBeUndefined();
        external.wrapper.unmount();

        const newTab = await mountAction(action({ url: '/guide', openInNewTab: true }));
        const link = newTab.wrapper.find('a');

        expect(link.attributes('data-inertia-link')).toBeUndefined();
        expect(link.attributes('target')).toBe('_blank');
        expect(link.attributes('rel')).toBe('noopener noreferrer');
        newTab.wrapper.unmount();
    });

    it('runs through a runner outside a form', async () => {
        const runner = {
            mountAction: vi.fn(),
            runAction: vi.fn().mockResolvedValue({}),
            processing: ref(false),
            t: (text: string) => text,
        };
        const node = action();
        const wrapper = mount(XqAction, { props: { node, runner } });

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(runner.runAction).toHaveBeenCalledWith(node, undefined);
        wrapper.unmount();
    });
});
