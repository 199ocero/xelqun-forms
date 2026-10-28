import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { RequestQueue } from '../../resources/js/core/queue';
import type { UpdatePatch, UpdateRequest } from '../../resources/js/schema/types';

function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((done) => (resolve = done));

    return { promise, resolve };
}

function makeQueue() {
    const sent: Array<{
        request: UpdateRequest;
        signal: AbortSignal;
        reply: ReturnType<typeof deferred<UpdatePatch>>;
    }> = [];
    const applied: UpdatePatch[] = [];
    const queue = new RequestQueue({
        buildRequest: (seq, changed) => ({
            token: 't',
            seq,
            state: { data: {} },
            changed,
            deps: {},
        }),
        snapshotVersions: () => new Map(),
        send: (request, signal) => {
            const reply = deferred<UpdatePatch>();
            sent.push({ request, signal, reply });
            signal.addEventListener('abort', () =>
                reply.resolve({ ...patch(request.seq), aborted: true } as any),
            );

            return reply.promise.then((value: any) => {
                if (value.aborted) {
                    throw new DOMException('Aborted', 'AbortError');
                }

                return value;
            });
        },
        apply: (value) => applied.push(value),
    });

    return { queue, sent, applied };
}

function patch(seq: number): UpdatePatch {
    return { $xq: 1, seq, set: {}, schema: {}, errors: {} };
}

describe('RequestQueue', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('batches changes inside the debounce window into one request', async () => {
        const { queue, sent } = makeQueue();

        queue.notify('data.a', { mode: 'debounce', ms: 400 });
        await vi.advanceTimersByTimeAsync(200);
        queue.notify('data.b', { mode: 'debounce', ms: 400 });
        await vi.advanceTimersByTimeAsync(399);

        expect(sent).toHaveLength(0);

        await vi.advanceTimersByTimeAsync(1);

        expect(sent).toHaveLength(1);
        expect(sent[0].request.changed).toEqual(['data.a', 'data.b']);
    });

    it('waits for blur in blur mode', async () => {
        const { queue, sent } = makeQueue();

        queue.notify('data.a', { mode: 'blur', ms: null });
        await vi.advanceTimersByTimeAsync(1000);
        expect(sent).toHaveLength(0);

        queue.blur('data.a');
        await vi.advanceTimersByTimeAsync(0);
        expect(sent).toHaveLength(1);
    });

    it('aborts the request in flight and carries its paths into the next one', async () => {
        const { queue, sent, applied } = makeQueue();

        queue.notify('data.country', { mode: 'change', ms: null });
        await vi.advanceTimersByTimeAsync(0);
        queue.notify('data.name', { mode: 'change', ms: null });
        await vi.advanceTimersByTimeAsync(0);

        expect(sent).toHaveLength(2);
        expect(sent[0].signal.aborted).toBe(true);
        expect(sent[1].request.changed).toEqual(['data.name', 'data.country']);

        sent[1].reply.resolve(patch(2));
        await vi.advanceTimersByTimeAsync(0);

        expect(applied.map((value) => value.seq)).toEqual([2]);
        expect(queue.log.map((entry) => entry.status)).toEqual(['ok', 'aborted']);
    });

    it('ignores responses for anything but the latest sequence number', async () => {
        const { queue, sent, applied } = makeQueue();

        queue.notify('data.a', { mode: 'change', ms: null });
        await vi.advanceTimersByTimeAsync(0);
        sent[0].reply.resolve(patch(99));
        await vi.advanceTimersByTimeAsync(0);

        expect(applied).toHaveLength(0);
        expect(queue.log[0].status).toBe('stale');
    });

    it('resolves idle() once pending and in-flight work is done', async () => {
        const { queue, sent } = makeQueue();
        let idle = false;

        queue.notify('data.a', { mode: 'debounce', ms: 400 });
        void queue.idle().then(() => (idle = true));
        await vi.advanceTimersByTimeAsync(0);

        expect(sent).toHaveLength(1);
        expect(idle).toBe(false);

        sent[0].reply.resolve(patch(1));
        await vi.advanceTimersByTimeAsync(0);

        expect(idle).toBe(true);
    });
});
