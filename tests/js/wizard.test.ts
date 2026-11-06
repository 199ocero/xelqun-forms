import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import type { SchemaNode } from '../../resources/js/schema/types';
import { field, schema } from './fixtures';

const inertia = vi.hoisted(() => ({ replace: vi.fn(), visit: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ router: inertia }));

function step(key: string, children: SchemaNode[], extra: Partial<SchemaNode> = {}): SchemaNode {
    return {
        key,
        type: 'step',
        hidden: false,
        path: null,
        label: `Step ${key}`,
        props: { slug: `step-${key}` },
        children,
        ...extra,
    };
}

function required(path: string): Partial<SchemaNode> {
    return {
        required: true,
        validation: {
            attribute: path,
            rules: [{ rule: 'required', message: 'The :attribute field is required.' }],
        },
    };
}

function wizard(props = {}, steps?: SchemaNode[]): SchemaNode {
    return {
        key: 'w',
        type: 'wizard',
        hidden: false,
        path: null,
        props: { startKey: 'a', skippable: false, queryKey: null, ...props },
        children: steps ?? [
            step('a', [field('data.name')]),
            step('b', [field('data.plan')]),
            step('c', [field('data.notes')]),
        ],
    };
}

function respond(body: unknown, status = 200) {
    return Promise.resolve(new Response(JSON.stringify(body), { status }));
}

function patch(extra = {}) {
    return { $xq: 1, seq: 0, set: {}, schema: {}, errors: {}, ...extra };
}

async function mountWizard(node = wizard(), data: Record<string, unknown> = {}, action?: string) {
    const store = createFormStore(schema([node], { name: null, plan: null, notes: null, ...data }));
    const wrapper = mount(XqForm, { props: { form: store, action }, attachTo: document.body });

    await flushPromises();

    return { store, wrapper };
}

const panel = (wrapper: any) => wrapper.find('section:not([hidden])').attributes('aria-label');
const next = (wrapper: any) => wrapper.find('[data-xq-wizard-next]');

describe('wizard', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(() => respond(patch()));
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        inertia.replace.mockReset();
        inertia.visit.mockReset();
    });

    it('opens on the start step, and keeps the other panels mounted', async () => {
        const { wrapper } = await mountWizard(wizard({ startKey: 'b' }));

        expect(panel(wrapper)).toBe('Step b');
        expect(wrapper.findAll('section')).toHaveLength(3);
        wrapper.unmount();
    });

    it('asks the server to check the step, and moves on when it passes', async () => {
        const { wrapper } = await mountWizard();

        await next(wrapper).trigger('click');
        await flushPromises();

        const body = JSON.parse(fetchMock.mock.calls[0][1].body);

        expect(fetchMock.mock.calls[0][0]).toBe('/_xelqun/forms/validate');
        expect(body).toMatchObject({ token: 'token', step: 'a', state: { data: { name: null } } });
        expect(panel(wrapper)).toBe('Step b');
        wrapper.unmount();
    });

    it('stays on the step when the server finds errors', async () => {
        fetchMock.mockImplementation(() =>
            respond(patch({ errors: { 'data.name': ['Who are you?'] } })),
        );

        const { wrapper } = await mountWizard();

        await next(wrapper).trigger('click');
        await flushPromises();

        expect(panel(wrapper)).toBe('Step a');
        expect(wrapper.text()).toContain('Who are you?');
        wrapper.unmount();
    });

    it('stays on the step when a hook refuses it', async () => {
        fetchMock.mockImplementation(() =>
            respond({ message: 'No', errors: { 'data.name': ['Not today.'] } }, 422),
        );

        const { wrapper } = await mountWizard();

        await next(wrapper).trigger('click');
        await flushPromises();

        expect(panel(wrapper)).toBe('Step a');
        expect(wrapper.text()).toContain('Not today.');
        wrapper.unmount();
    });

    it('stays on the step and says so when the request fails', async () => {
        fetchMock.mockImplementation(() => Promise.reject(new TypeError('offline')));

        const { store, wrapper } = await mountWizard();

        await next(wrapper).trigger('click');
        await flushPromises();

        expect(panel(wrapper)).toBe('Step a');
        expect(store.liveError.value).toContain('Check your connection');
        expect(store.validating.value).toBe(false);
        wrapper.unmount();
    });

    it('takes the failure message away when a retry works', async () => {
        fetchMock.mockImplementationOnce(() => Promise.reject(new TypeError('offline')));

        const { store, wrapper } = await mountWizard();

        await next(wrapper).trigger('click');
        await flushPromises();
        await next(wrapper).trigger('click');
        await flushPromises();

        expect(store.liveError.value).toBeNull();
        expect(panel(wrapper)).toBe('Step b');
        wrapper.unmount();
    });

    it('applies what the step hooks set', async () => {
        fetchMock.mockImplementation(() => respond(patch({ set: { 'data.plan': 'pro' } })));

        const { store, wrapper } = await mountWizard();

        await next(wrapper).trigger('click');
        await flushPromises();

        expect(store.get('data.plan')).toBe('pro');
        wrapper.unmount();
    });

    it('replaces the errors of this step and leaves those of others', async () => {
        const { store, wrapper } = await mountWizard();

        store.setErrors({ 'data.name': 'Old', 'data.plan': 'Other step' });
        await next(wrapper).trigger('click');
        await flushPromises();

        expect(store.errors).toEqual({ 'data.plan': 'Other step' });
        wrapper.unmount();
    });

    it('stops in the browser first, with no request', async () => {
        const { store, wrapper } = await mountWizard(
            wizard({}, [
                step('a', [field('data.name', 'text-input', required('Name'))]),
                step('b', []),
            ]),
        );

        await next(wrapper).trigger('click');
        await flushPromises();

        expect(fetchMock).not.toHaveBeenCalled();
        expect(store.errors['data.name']).toBe('The Name field is required.');
        expect(panel(wrapper)).toBe('Step a');
        wrapper.unmount();
    });

    it('waits for a running upload before it checks', async () => {
        const { store, wrapper } = await mountWizard();
        let finish = () => {};

        store.trackUpload(new Promise<void>((resolve) => (finish = resolve)));
        await next(wrapper).trigger('click');
        await flushPromises();

        expect(fetchMock).not.toHaveBeenCalled();

        finish();
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledOnce();
        wrapper.unmount();
    });

    it('sends only one check for a double click', async () => {
        const { wrapper } = await mountWizard();

        next(wrapper).trigger('click');
        next(wrapper).trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledOnce();
        wrapper.unmount();
    });

    it('goes back with no check, and only then can it reach a later step', async () => {
        const { wrapper } = await mountWizard();
        const titles = () => wrapper.findAll('ol button');

        expect(titles()[1].attributes('disabled')).toBeDefined();

        await next(wrapper).trigger('click');
        await flushPromises();
        fetchMock.mockClear();

        await wrapper.find('[data-xq-wizard-back]').trigger('click');
        await flushPromises();

        expect(panel(wrapper)).toBe('Step a');
        expect(fetchMock).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('lets a skippable wizard jump anywhere with no check', async () => {
        const { wrapper } = await mountWizard(wizard({ skippable: true }));

        await wrapper.findAll('ol button')[2].trigger('click');
        await flushPromises();

        expect(panel(wrapper)).toBe('Step c');

        await wrapper.find('[data-xq-wizard-back]').trigger('click');
        await next(wrapper).trigger('click');
        await flushPromises();

        expect(fetchMock).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('moves on instead of saving when the form is submitted early', async () => {
        const { wrapper } = await mountWizard(wizard(), {}, '/save');

        expect(wrapper.find('button[type="submit"]').exists()).toBe(false);

        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(panel(wrapper)).toBe('Step b');
        expect(inertia.visit).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('shows the submit button on the last step', async () => {
        const { wrapper } = await mountWizard(wizard({ startKey: 'c' }), {}, '/save');

        expect(wrapper.find('[data-xq-wizard-next]').exists()).toBe(false);
        expect(wrapper.find('button[type="submit"]').exists()).toBe(true);
        wrapper.unmount();
    });

    it('opens the step with the first error after the final submit', async () => {
        const { store, wrapper } = await mountWizard(wizard({ startKey: 'c' }));

        store.setErrors({ 'data.name': 'Missing' });
        store.focusFirstError();
        await flushPromises();

        expect(panel(wrapper)).toBe('Step a');
        wrapper.unmount();
    });

    it('leaves out a step whose rule hides it, and moves off it if it goes', async () => {
        const rules = { visible: { op: 'eq' as const, path: 'data.on', value: true } };
        const { store, wrapper } = await mountWizard(
            wizard({ startKey: 'b' }, [step('a', []), step('b', [], { rules }), step('c', [])]),
            { on: true },
        );

        expect(panel(wrapper)).toBe('Step b');

        store.set('data.on', false);
        await flushPromises();

        expect(wrapper.findAll('ol li')).toHaveLength(2);
        expect(panel(wrapper)).toBe('Step a');
        wrapper.unmount();
    });

    it('opens the right step when an earlier one is hidden', async () => {
        const { wrapper } = await mountWizard(
            wizard({ startKey: 'b' }, [
                step('a', [], { hidden: true }),
                step('b', []),
                step('c', []),
            ]),
        );

        expect(panel(wrapper)).toBe('Step b');
        wrapper.unmount();
    });

    it('moves past a step that the current step hid', async () => {
        fetchMock.mockImplementation(() => respond(patch({ set: { 'data.skip': true } })));

        const rules = { visible: { op: 'neq' as const, path: 'data.skip', value: true } };
        const { wrapper } = await mountWizard(
            wizard({}, [step('a', []), step('b', [], { rules }), step('c', [])]),
            { skip: false },
        );

        await next(wrapper).trigger('click');
        await flushPromises();

        expect(panel(wrapper)).toBe('Step c');
        wrapper.unmount();
    });

    it('stays put when the last step left is the current one', async () => {
        fetchMock.mockImplementation(() => respond(patch({ set: { 'data.skip': true } })));

        const rules = { visible: { op: 'neq' as const, path: 'data.skip', value: true } };
        const { wrapper } = await mountWizard(
            wizard({}, [step('a', []), step('b', [], { rules })]),
            { skip: false },
        );

        await next(wrapper).trigger('click');
        await flushPromises();

        expect(panel(wrapper)).toBe('Step a');
        wrapper.unmount();
    });

    it('keeps the step in the URL', async () => {
        const { wrapper } = await mountWizard(wizard({ queryKey: 'page' }));

        await next(wrapper).trigger('click');
        await flushPromises();

        expect(inertia.replace.mock.calls[0][0]).toMatchObject({
            preserveState: true,
            url: expect.stringContaining('page=step-b'),
        });
        wrapper.unmount();
    });
});
