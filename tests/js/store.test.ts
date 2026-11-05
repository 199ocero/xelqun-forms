import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { createFormStore } from '../../resources/js/core/store';
import type { UpdatePatch, UpdateRequest } from '../../resources/js/schema/types';
import { field, orderSchema, schema } from './fixtures';

function storeWithServer(reply: (request: UpdateRequest) => Partial<UpdatePatch>) {
    const requests: UpdateRequest[] = [];
    const store = createFormStore(orderSchema(), {
        send: async (_url, request) => {
            requests.push(request);

            return { $xq: 1, seq: request.seq, set: {}, schema: {}, errors: {}, ...reply(request) };
        },
    });

    return { store, requests };
}

describe('form store', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('finds the first error in the order rows are shown after a move', () => {
        const store = createFormStore(
            schema(
                [
                    field('data.items', 'repeater', {
                        props: {
                            rows: {
                                a: [field('data.items.a.qty')],
                                b: [field('data.items.b.qty')],
                            },
                        },
                    }),
                ],
                { items: { a: { qty: 1 }, b: { qty: 2 } } },
            ),
        );

        store.moveRow(store.nodes.get('data.items')!, 'b', -1);
        store.errors['data.items.a.qty'] = 'Required';
        store.errors['data.items.b.qty'] = 'Required';
        store.focusFirstError();

        expect(store.focusRequest.value?.path).toBe('data.items.b.qty');
    });

    it('gives rows of a nested repeater their own paths', async () => {
        const store = createFormStore(
            schema(
                [
                    field('data.sections', 'repeater', {
                        props: {
                            rowPlaceholder: '{row}',
                            newRow: { lines: {} },
                            template: [
                                field('data.sections.{row}.lines', 'repeater', {
                                    props: {
                                        rowPlaceholder: '{row:1}',
                                        newRow: { qty: 1 },
                                        template: [field('data.sections.{row}.lines.{row:1}.qty')],
                                    },
                                }),
                            ],
                        },
                    }),
                ],
                { sections: {} },
            ),
        );

        const section = store.addRow(store.nodes.get('data.sections')!);
        await nextTick();

        const line = store.addRow(store.nodes.get(`data.sections.${section}.lines`)!);
        await nextTick();

        expect(store.nodes.has(`data.sections.${section}.lines.${line}.qty`)).toBe(true);
    });

    it('evaluates visibility conditions locally', () => {
        const { store, requests } = storeWithServer(() => ({}));
        const company = store.nodes.get('data.company')!;

        expect(store.isVisible(company)).toBe(false);

        store.set('data.type', 'business');

        expect(store.isVisible(company)).toBe(true);
        expect(requests).toHaveLength(0);
    });

    it('computes row subtotals and the grand total', async () => {
        const { store } = storeWithServer(() => ({}));

        expect(store.get('data.items.r1.subtotal')).toBe(10);
        await nextTick();
        expect(store.get('data.total')).toBe(10);

        store.set('data.items.r1.qty', 3);
        await nextTick();
        await nextTick();

        expect(store.get('data.items.r1.subtotal')).toBe(15);
        expect(store.get('data.total')).toBe(15);
    });

    it('adds rows from the template and includes them in totals', async () => {
        const { store } = storeWithServer(() => ({}));
        const repeater = store.nodes.get('data.items')!;

        const key = store.addRow(repeater);
        store.set(`data.items.${key}.price`, 4);
        await nextTick();
        await nextTick();

        expect(store.rowKeys(repeater)).toEqual(['r1', key]);
        expect(store.nodes.has(`data.items.${key}.subtotal`)).toBe(true);
        expect(store.get(`data.items.${key}.subtotal`)).toBe(4);
        expect(store.get('data.total')).toBe(14);
    });

    it('removes rows, their nodes and their errors', async () => {
        const { store } = storeWithServer(() => ({}));
        const repeater = store.nodes.get('data.items')!;

        store.setErrors({ 'data.items.r1.price': 'Required' });
        store.removeRow(repeater, 'r1');
        await nextTick();

        expect(store.rowKeys(repeater)).toEqual([]);
        expect(store.nodes.has('data.items.r1.qty')).toBe(false);
        expect(store.errors).toEqual({});
    });

    it('reorders rows', () => {
        const { store } = storeWithServer(() => ({}));
        const repeater = store.nodes.get('data.items')!;
        const key = store.addRow(repeater);

        store.moveRow(repeater, key, -1);

        expect(store.rowKeys(repeater)).toEqual([key, 'r1']);
    });

    it('calls the server only for live fields and dependencies', async () => {
        const { store, requests } = storeWithServer(() => ({}));

        store.set('data.company', 'Acme');
        await vi.advanceTimersByTimeAsync(1000);
        expect(requests).toHaveLength(0);

        store.set('data.country', 'PH');
        await vi.advanceTimersByTimeAsync(0);

        expect(requests).toHaveLength(1);
        expect(requests[0].changed).toEqual(['data.country']);
        expect(requests[0].deps).toEqual({ 'data.state': ['data.country'] });
    });

    it('applies server patches in place', async () => {
        const { store } = storeWithServer(() => ({
            set: { 'data.state': null },
            schema: { 'data.state': { props: { options: [{ value: 1, label: 'Cebu' }] } } },
        }));
        const stateNode = store.nodes.get('data.state')!;

        store.set('data.state', 'old');
        store.set('data.country', 'PH');
        await vi.advanceTimersByTimeAsync(0);

        expect(store.nodes.get('data.state')).toBe(stateNode);
        expect(stateNode.props!.options).toEqual([{ value: 1, label: 'Cebu' }]);
        expect(store.get('data.state')).toBeNull();
    });

    it('never overwrites a field the user edited after the request was sent', async () => {
        let release!: () => void;
        const gate = new Promise<void>((resolve) => (release = resolve));
        const store = createFormStore(orderSchema(), {
            send: async (_url, request) => {
                await gate;

                return {
                    $xq: 1,
                    seq: request.seq,
                    set: { 'data.state': 'from-server' },
                    schema: {},
                    errors: {},
                };
            },
        });

        store.set('data.country', 'PH');
        await vi.advanceTimersByTimeAsync(0);
        store.set('data.state', 'typed-by-user');
        release();
        await vi.advanceTimersByTimeAsync(0);

        expect(store.get('data.state')).toBe('typed-by-user');
    });

    it('clears a field error when the user edits it', () => {
        const { store } = storeWithServer(() => ({}));

        store.setErrors({ 'data.company': ['Required'] });
        expect(store.errors['data.company']).toBe('Required');

        store.set('data.company', 'Acme');
        expect(store.errors['data.company']).toBeUndefined();
    });

    it('flags unsupported schema versions', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {});
        const store = createFormStore({ ...orderSchema(), $xq: 99 });

        expect(store.fatalError.value).toContain('schema v99');
        error.mockRestore();
    });
});
