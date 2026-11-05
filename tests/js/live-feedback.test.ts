import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import XqForm from '../../resources/js/components/XqForm.vue';
import { HttpError } from '../../resources/js/core/http';
import { createFormStore, LOADING_INDICATOR_DELAY } from '../../resources/js/core/store';
import type { UpdatePatch, UpdateRequest } from '../../resources/js/schema/types';
import { field, schema } from './fixtures';

const spinner = '.motion-safe\\:animate-spin';

function patch(seq: number): UpdatePatch {
    return { $xq: 1, seq, set: {}, schema: {}, errors: {} };
}

function setup(send: (url: string, request: UpdateRequest) => Promise<UpdatePatch>) {
    const store = createFormStore(
        schema(
            [
                field('data.country', 'select', { props: { options: [] } }),
                field('data.state', 'select', { props: { options: [] }, deps: ['data.country'] }),
                field('data.phone'),
            ],
            { country: null, state: null, phone: null },
        ),
        { send },
    );
    const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

    return { store, wrapper };
}

describe('live update feedback', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.spyOn(console, 'error').mockImplementation(() => {});
    });
    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    async function changeCountry(store: ReturnType<typeof setup>['store']): Promise<void> {
        store.set('data.country', 1);
        await vi.advanceTimersByTimeAsync(0);
        await flushPromises();
    }

    it('marks only the fields that depend on the request in flight', async () => {
        let reply!: (value: UpdatePatch) => void;
        const { store, wrapper } = setup(
            () => new Promise<UpdatePatch>((resolve) => (reply = resolve)),
        );

        await changeCountry(store);

        expect(wrapper.find('[data-xq-path="data.state"]').attributes('aria-busy')).toBe('true');
        expect(wrapper.find('[data-xq-path="data.phone"]').attributes('aria-busy')).toBeUndefined();

        reply(patch(1));
        await flushPromises();

        expect(wrapper.find('[data-xq-path="data.state"]').attributes('aria-busy')).toBeUndefined();
        wrapper.unmount();
    });

    it('never shows a spinner for a request that finishes within the delay', async () => {
        let reply!: (value: UpdatePatch) => void;
        const { store, wrapper } = setup(
            () => new Promise<UpdatePatch>((resolve) => (reply = resolve)),
        );

        await changeCountry(store);
        await vi.advanceTimersByTimeAsync(LOADING_INDICATOR_DELAY - 1);

        expect(wrapper.find(spinner).exists()).toBe(false);

        reply(patch(1));
        await flushPromises();
        await vi.advanceTimersByTimeAsync(LOADING_INDICATOR_DELAY);

        expect(wrapper.find(spinner).exists()).toBe(false);
        wrapper.unmount();
    });

    it('keeps the spinner up when a new request replaces the one in flight', async () => {
        const replies: Array<(value: UpdatePatch) => void> = [];
        const { store, wrapper } = setup(
            () => new Promise<UpdatePatch>((resolve) => replies.push(resolve)),
        );

        await changeCountry(store);
        await vi.advanceTimersByTimeAsync(LOADING_INDICATOR_DELAY);
        await changeCountry(store);

        expect(wrapper.find(spinner).exists()).toBe(true);

        replies[1](patch(2));
        await flushPromises();

        expect(wrapper.find(spinner).exists()).toBe(false);
        wrapper.unmount();
    });

    it.each([
        ['select', { props: { options: [] } }, '[data-slot="xq-select"]'],
        ['select', { props: { options: [], searchable: true } }, '[data-slot="xq-combobox"]'],
        ['text-input', {}, '.h-9'],
        ['textarea', {}, '.relative'],
        ['date-picker', {}, 'button'],
    ])(
        'shows a slow request inside a %s control, not on its label',
        async (type, extra, control) => {
            const store = createFormStore(
                schema(
                    [
                        field('data.country'),
                        field('data.value', type, { ...extra, deps: ['data.country'] }),
                    ],
                    { country: null, value: null },
                ),
            );
            const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

            // Date pickers are lazy-loaded.
            await vi.dynamicImportSettled();
            await flushPromises();

            store.queue.loadingPaths.value = ['data.country'];
            await vi.advanceTimersByTimeAsync(LOADING_INDICATOR_DELAY);

            const target = wrapper.find('[data-xq-path="data.value"]');

            expect(target.find(`${control} ${spinner}`).exists()).toBe(true);
            expect(target.find(`label ${spinner}`).exists()).toBe(false);
            wrapper.unmount();
        },
    );

    it('falls back to the label for fields without a control to hold the spinner', async () => {
        const store = createFormStore(
            schema(
                [
                    field('data.country'),
                    field('data.value', 'radio', {
                        props: { options: [] },
                        deps: ['data.country'],
                    }),
                ],
                { country: null, value: null },
            ),
        );
        const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

        store.queue.loadingPaths.value = ['data.country'];
        await vi.advanceTimersByTimeAsync(LOADING_INDICATOR_DELAY);

        expect(wrapper.find(`[data-xq-path="data.value"] label ${spinner}`).exists()).toBe(true);
        wrapper.unmount();
    });

    it('tells the user when an update fails and recovers on retry', async () => {
        const send = vi
            .fn<(url: string, request: UpdateRequest) => Promise<UpdatePatch>>()
            .mockRejectedValueOnce(new HttpError(500, { message: 'Server error' }))
            .mockImplementation(async (_url, request) => patch(request.seq));
        const { store, wrapper } = setup(send);

        await changeCountry(store);

        expect(wrapper.find('[role="alert"]').text()).toContain('could not be updated');

        await wrapper.find('[role="alert"] button').trigger('click');
        await flushPromises();

        expect(send).toHaveBeenCalledTimes(2);
        expect(send.mock.calls[1][1].changed).toEqual(['data.country']);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('resends a failed change along with the next one', async () => {
        const send = vi
            .fn<(url: string, request: UpdateRequest) => Promise<UpdatePatch>>()
            .mockRejectedValueOnce(new TypeError('Failed to fetch'))
            .mockImplementation(async (_url, request) => patch(request.seq));
        const { store, wrapper } = setup(send);

        await changeCountry(store);
        store.set('data.state', 2);
        store.queue.notify('data.state', { mode: 'change', ms: null });
        await vi.advanceTimersByTimeAsync(0);
        await flushPromises();

        expect(send.mock.calls[1][1].changed).toEqual(
            expect.arrayContaining(['data.country', 'data.state']),
        );
        expect(store.liveError.value).toBeNull();
        wrapper.unmount();
    });

    it('keeps the expired message for a 419 instead of offering a retry', async () => {
        const { store, wrapper } = setup(() =>
            Promise.reject(new HttpError(419, { message: 'Page expired' })),
        );

        await changeCountry(store);

        expect(store.fatalError.value).toContain('expired');
        expect(store.liveError.value).toBeNull();
        wrapper.unmount();
    });

    it('does not treat an aborted request as a failure', async () => {
        const send = vi.fn(
            (_url: string, request: UpdateRequest, signal?: AbortSignal) =>
                new Promise<UpdatePatch>((resolve, reject) => {
                    signal?.addEventListener('abort', () =>
                        reject(new DOMException('Aborted', 'AbortError')),
                    );
                    setTimeout(() => resolve(patch(request.seq)), 50);
                }),
        );
        const { store, wrapper } = setup(send);

        await changeCountry(store);
        store.set('data.country', 2);
        await vi.advanceTimersByTimeAsync(100);
        await nextTick();

        expect(store.liveError.value).toBeNull();
        wrapper.unmount();
    });
});
