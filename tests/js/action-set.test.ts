import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import XqActions from '../../resources/js/components/XqActions.vue';
import type { FormSchema, SchemaNode } from '../../resources/js/schema/types';
import { schema } from './fixtures';

const { reload, visit } = vi.hoisted(() => ({ reload: vi.fn(), visit: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { reload, visit },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

function action(key: string, label: string, extra: Partial<SchemaNode> = {}): SchemaNode {
    return {
        key,
        type: 'action',
        hidden: false,
        path: null,
        props: { label, heading: label, style: 'button' },
        children: [],
        deps: [],
        ...extra,
    };
}

function actions(nodes: SchemaNode[], token = 'first'): FormSchema {
    return schema(nodes, {}, token);
}

function respond(body: unknown, status = 200): void {
    vi.mocked(fetch).mockResolvedValueOnce(new Response(JSON.stringify(body), { status }));
}

const patch = { $xq: 1, seq: 0, set: {}, schema: {}, errors: {} };

async function click(wrapper: ReturnType<typeof mount>): Promise<void> {
    await wrapper.find('[data-xq-action] button').trigger('click');
    await flushPromises();
}

describe('XqActions', () => {
    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn());
        reload.mockClear();
        visit.mockClear();
        reload.mockImplementation((options) => options!.onFinish!({} as never));
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('shows the actions the viewer may use, with no form around them', () => {
        const wrapper = mount(XqActions, {
            props: {
                actions: actions([
                    action('data#0', 'Publish'),
                    action('data#1', 'Secret', { hidden: true }),
                ]),
            },
        });

        expect(wrapper.findAll('[data-xq-action]')).toHaveLength(1);
        expect(wrapper.text()).toContain('Publish');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('reloads the page after an action runs', async () => {
        const wrapper = mount(XqActions, {
            props: { actions: actions([action('data#0', 'Publish')]) },
        });

        respond({ ...patch, result: null });
        await click(wrapper);

        expect(reload).toHaveBeenCalledOnce();
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('leaves for the redirect, and does not reload a page that may be gone', async () => {
        const wrapper = mount(XqActions, {
            props: { actions: actions([action('data#0', 'Delete')]) },
        });

        respond({ ...patch, result: null, redirect: '/posts' });
        await click(wrapper);

        expect(visit).toHaveBeenCalledWith('/posts');
        expect(reload).not.toHaveBeenCalled();
    });

    it('stays busy until the page has the new data', async () => {
        let finishReload = (): void => {};

        reload.mockImplementation((options) => {
            finishReload = () => options!.onFinish!({} as never);
        });

        const wrapper = mount(XqActions, {
            props: { actions: actions([action('data#0', 'Publish')]) },
        });

        respond({ ...patch, result: null });
        await click(wrapper);

        expect(wrapper.find('button').attributes('aria-busy')).toBe('true');

        finishReload();
        await flushPromises();

        expect(wrapper.find('button').attributes('aria-busy')).toBeUndefined();
    });

    it('keeps the same button when the page reloads, so focus stays on it', async () => {
        const wrapper = mount(XqActions, {
            props: { actions: actions([action('data#0', 'Publish')]) },
        });
        const button = wrapper.find('button').element;

        await wrapper.setProps({ actions: actions([action('data#0', 'Unpublish')], 'second') });

        expect(wrapper.find('button').element).toBe(button);
        expect(wrapper.text()).toContain('Unpublish');
    });

    it('shows a validation error and keeps the page', async () => {
        const wrapper = mount(XqActions, {
            props: { actions: actions([action('data#0', 'Publish')]) },
        });

        respond({ message: 'No', errors: { data: ['The server said no.'] } }, 422);
        await click(wrapper);

        expect(wrapper.find('[role="alert"]').text()).toContain('The server said no.');
        expect(reload).not.toHaveBeenCalled();
    });

    it('explains a failure without talking about fields', async () => {
        const wrapper = mount(XqActions, {
            props: { actions: actions([action('data#0', 'Publish')]) },
        });

        respond({ message: 'Forbidden' }, 403);
        await click(wrapper);

        expect(wrapper.find('[role="alert"]').text()).toContain('That action could not run.');
    });

    it('sends the new token after the page reloads', async () => {
        const wrapper = mount(XqActions, {
            props: { actions: actions([action('data#0', 'Publish')]) },
        });

        await wrapper.setProps({ actions: actions([action('data#0', 'Unpublish')], 'second') });
        expect(wrapper.text()).toContain('Unpublish');

        respond({ ...patch, result: null });
        await click(wrapper);

        expect(JSON.parse(vi.mocked(fetch).mock.calls[0][1]!.body as string).token).toBe('second');
    });

    it('clears a failure when the page reloads', async () => {
        const wrapper = mount(XqActions, {
            props: { actions: actions([action('data#0', 'Publish')]) },
        });

        respond({ message: 'Forbidden' }, 403);
        await click(wrapper);
        await wrapper.setProps({ actions: actions([action('data#0', 'Publish')], 'second') });

        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });
});
