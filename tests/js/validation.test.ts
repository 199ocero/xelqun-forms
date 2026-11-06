import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import type { ClientRule, SchemaNode } from '../../resources/js/schema/types';
import { field, schema } from './fixtures';

const visit = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({ router: { visit } }));

function checked(path: string, rule: Omit<ClientRule, 'message'>, extra: Partial<SchemaNode> = {}) {
    return field(path, 'text-input', {
        required: rule.rule === 'required',
        validation: {
            attribute: 'Thing',
            rules: [{ ...rule, message: `The :attribute failed ${rule.rule}.` } as ClientRule],
        },
        ...extra,
    });
}

const required = { rule: 'required' } as const;

function repeater(rows: Record<string, SchemaNode[]>, itemLabel = 'Item'): SchemaNode {
    return field('data.items', 'repeater', {
        containsRows: true,
        props: { template: [], rows, itemLabel, newRow: {} },
    });
}

function run(components: SchemaNode[], data: Record<string, any> = {}) {
    const store = createFormStore(schema(components, data, null));

    return { store, submit: () => store.submit('/save') };
}

describe('frontend validation on submit', () => {
    afterEach(() => visit.mockReset());

    it('stops before the server and shows the error', async () => {
        const { store, submit } = run([checked('data.name', required)], { name: null });

        await submit();

        expect(visit).not.toHaveBeenCalled();
        expect(store.errors['data.name']).toBe('The Thing failed required.');
        expect(store.processing.value).toBe(false);
    });

    it('tells the page the submit failed and finished', async () => {
        const { store } = run([checked('data.name', required)], { name: null });
        const onError = vi.fn();
        const onFinish = vi.fn();

        await store.submit('/save', { onError, onFinish });

        expect(onError).toHaveBeenCalledWith({ 'data.name': 'The Thing failed required.' });
        expect(onFinish).toHaveBeenCalledOnce();
    });

    it('goes to the server when everything passes', async () => {
        const { submit } = run([checked('data.name', required)], { name: 'Ada' });

        await submit();

        expect(visit).toHaveBeenCalledOnce();
    });

    it('goes to the server when no field has rules', async () => {
        const { submit } = run([field('data.name', 'text-input', { required: true })], {
            name: null,
        });

        await submit();

        expect(visit).toHaveBeenCalledOnce();
    });

    it.each([
        ['hidden by a rule', { rules: { visible: { op: 'eq', path: 'data.on', value: true } } }],
        ['hidden by the server', { hidden: true }],
        ['disabled', { disabled: true }],
        ['read-only', { readOnly: true }],
        ['computed', { computed: { src: '1', ast: { lit: null } } }],
    ])('skips a field that is %s, like the server does', async (_, extra) => {
        const { store, submit } = run([checked('data.name', required, extra as any)], {
            name: null,
        });

        await submit();

        expect(Object.keys(store.errors)).toEqual([]);
        expect(visit).toHaveBeenCalledOnce();
    });

    it('skips the fields inside a hidden layout', async () => {
        const section: SchemaNode = {
            key: 's',
            type: 'section',
            hidden: false,
            path: null,
            rules: { visible: { op: 'eq', path: 'data.on', value: true } },
            props: {},
            children: [checked('data.name', required)],
        };
        const { submit } = run([section], { name: null, on: false });

        await submit();

        expect(visit).toHaveBeenCalledOnce();
    });

    it('follows requiredWhen', async () => {
        const rule = { op: 'eq' as const, path: 'data.kind', value: 'b' };
        const node = checked('data.vat', required, {
            required: false,
            rules: { visible: null, required: rule, disabled: null },
        });
        const { store, submit } = run([node], { vat: null, kind: 'a' });

        await submit();
        expect(visit).toHaveBeenCalledOnce();
        visit.mock.calls[0][1].onFinish();

        store.set('data.kind', 'b');
        await store.submit('/save');

        expect(store.errors['data.vat']).toBe('The Thing failed required.');
    });

    it('fills every form of the placeholder, and leaves odd labels alone', async () => {
        const node = field('data.a', 'text-input', {
            required: true,
            validation: {
                attribute: 'cost $& fee',
                rules: [{ rule: 'required', message: ':Attribute, :attribute, :ATTRIBUTE' }],
            },
        });
        const { store, submit } = run([node], { a: null });

        await submit();

        expect(store.errors['data.a']).toBe('Cost $& fee, cost $& fee, COST $& FEE');
    });

    it('names the row in the message, the way the server does', async () => {
        const row = (key: string) => [checked(`data.items.${key}.qty`, required)];
        const { store, submit } = run([repeater({ r1: row('r1'), r2: row('r2') })], {
            items: { r1: { qty: 1 }, r2: { qty: null } },
        });

        await submit();

        expect(store.errors).toEqual({
            'data.items.r2.qty': 'The Thing (Item 2) failed required.',
        });
    });

    it('names every level of nested rows', async () => {
        const inner = repeater({}, 'Line');

        inner.path = 'data.items.r1.lines';
        inner.key = inner.path;
        inner.props!.rows = { l1: [checked('data.items.r1.lines.l1.qty', required)] };

        const { store, submit } = run([repeater({ r1: [inner] }, 'Section')], {
            items: { r1: { lines: { l1: { qty: null } } } },
        });

        await submit();

        expect(store.errors['data.items.r1.lines.l1.qty']).toBe(
            'The Thing (Section 1, Line 1) failed required.',
        );
    });

    it('checks in the order of the rules, so a field shows one error', async () => {
        const node = field('data.age', 'text-input', {
            validation: {
                attribute: 'Age',
                rules: [
                    { rule: 'numeric', message: 'Numeric' },
                    { rule: 'min', type: 'numeric', value: 5, message: 'Min' },
                ],
            },
        });
        const { store, submit } = run([node], { age: 'abc' });

        await submit();

        expect(store.errors['data.age']).toBe('Numeric');
    });

    it('leaves an optional empty field alone', async () => {
        const { submit } = run([checked('data.age', { rule: 'numeric' })], { age: '' });

        await submit();

        expect(visit).toHaveBeenCalledOnce();
    });

    it('focuses the first field with an error', async () => {
        const store = createFormStore(
            schema([checked('data.name', required)], { name: null }, null),
        );
        const wrapper = mount(XqForm, {
            props: { form: store, action: '/save' },
            attachTo: document.body,
        });

        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(document.activeElement).toBe(wrapper.find('input').element);
        wrapper.unmount();
    });
});

describe('errors in a patch', () => {
    it('drops the error of a value the user has changed since', () => {
        const store = createFormStore(schema([field('data.email')], { email: 'old' }));
        const before = new Map([['data.email', 0]]);

        store.set('data.email', 'new');
        store.applyPatch(
            { $xq: 1, seq: 1, set: {}, schema: {}, errors: { 'data.email': ['Bad'] } },
            before,
        );

        expect(store.errors).toEqual({});
    });

    it('shows the error of a value that still stands', () => {
        const store = createFormStore(schema([field('data.email')], { email: 'old' }));

        store.applyPatch(
            { $xq: 1, seq: 1, set: {}, schema: {}, errors: { 'data.email': ['Bad'] } },
            new Map(),
        );

        expect(store.errors).toEqual({ 'data.email': 'Bad' });
    });
});
