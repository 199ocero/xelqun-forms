import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import XqForm from '../../resources/js/components/XqForm.vue';
import { HttpError } from '../../resources/js/core/http';
import { createFormStore } from '../../resources/js/core/store';
import type { UpdatePatch, UpdateRequest } from '../../resources/js/schema/types';
import { field, schema } from './fixtures';

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
        expect(wrapper.find('[data-xq-path="data.state"] svg').exists()).toBe(true);

        reply(patch(1));
        await flushPromises();

        expect(wrapper.find('[data-xq-path="data.state"]').attributes('aria-busy')).toBeUndefined();
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
