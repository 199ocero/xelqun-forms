import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import XqActions from '../../resources/js/components/XqActions.vue';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore, LOADING_INDICATOR_DELAY } from '../../resources/js/core/store';
import type { FormSchema, SchemaNode } from '../../resources/js/schema/types';
import { schema } from './fixtures';

const { reload } = vi.hoisted(() => ({ reload: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { reload, visit: vi.fn() },
    Link: { props: ['href'], template: '<a data-inertia-link :href="href"><slot /></a>' },
}));

function action(
    key: string,
    label: string,
    props = {},
    extra: Partial<SchemaNode> = {},
): SchemaNode {
    return {
        key,
        type: 'action',
        hidden: false,
        path: null,
        props: { label, heading: label, style: 'button', ...props },
        children: [],
        deps: [],
        ...extra,
    };
}

function group(children: SchemaNode[], props = {}): FormSchema {
    return schema(
        [
            {
                key: 'data#0',
                type: 'action-group',
                hidden: false,
                path: null,
                props,
                children,
                deps: [],
            },
        ],
        {},
        'token',
    );
}

const patch = { $xq: 1, seq: 0, set: {}, schema: {}, errors: {} };

function respond(body: unknown): void {
    vi.mocked(fetch).mockResolvedValueOnce(new Response(JSON.stringify(body), { status: 200 }));
}

function items(): HTMLElement[] {
    return [...document.querySelectorAll<HTMLElement>('[role="menuitem"]')];
}

describe('XqActionGroup', () => {
    let wrapper: ReturnType<typeof mount>;

    function render(actions: FormSchema): ReturnType<typeof mount> {
        wrapper = mount(XqActions, { props: { actions }, attachTo: document.body });

        return wrapper;
    }

    async function openMenu(): Promise<void> {
        await wrapper.find('[data-xq-action-group] button').trigger('keydown', { key: 'Enter' });
        await flushPromises();
    }

    async function choose(label: string): Promise<void> {
        items()
            .find((item) => item.textContent?.includes(label))!
            .click();
        await flushPromises();
    }

    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn());
        reload.mockImplementation((options) => options!.onFinish!({} as never));
    });

    afterEach(() => {
        wrapper.unmount();
        vi.unstubAllGlobals();
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('lists the actions the viewer may use and nothing else', async () => {
        render(
            group([
                action('data#0.edit', 'Edit'),
                action('data#0.secret', 'Secret', {}, { hidden: true }),
            ]),
        );

        await openMenu();

        expect(items().map((item) => item.textContent?.trim())).toEqual(['Edit']);
    });

    it('renders inside a form', async () => {
        wrapper = mount(XqForm, {
            props: { form: createFormStore(group([action('data#0.edit', 'Edit')])) },
            attachTo: document.body,
        });

        await openMenu();

        expect(items().map((item) => item.textContent?.trim())).toEqual(['Edit']);
    });

    it('leaves the button out when no action is left to show', () => {
        render(group([action('data#0.secret', 'Secret', {}, { hidden: true })]));

        expect(wrapper.find('[data-xq-action-group] button').exists()).toBe(false);
    });

    it('names an icon-only button for screen readers, and shows a label when it has one', () => {
        render(group([action('data#0.edit', 'Edit')]));

        expect(wrapper.find('button').attributes('aria-label')).toBe('Actions');

        wrapper.unmount();
        render(group([action('data#0.edit', 'Edit')], { label: 'More' }));

        expect(wrapper.find('button').text()).toBe('More');
        expect(wrapper.find('button').attributes('aria-label')).toBeUndefined();
    });

    it('runs an action and reloads the page', async () => {
        respond(patch);
        render(group([action('data#0.edit', 'Edit')]));

        await openMenu();
        await choose('Edit');

        const body = JSON.parse(vi.mocked(fetch).mock.calls[0][1]!.body as string);

        expect(body.action).toBe('data#0.edit');
        expect(reload).toHaveBeenCalledOnce();
    });

    it('keeps a confirmation open after the menu has closed', async () => {
        render(group([action('data#0.delete', 'Delete', { confirmation: true })]));

        await openMenu();
        await choose('Delete');

        expect(items()).toHaveLength(0);
        expect(document.querySelector('[role="alertdialog"]')?.textContent).toContain('Delete');
    });

    it('keeps a modal form open after the menu has closed', async () => {
        const form = schema([], {}, 'modal');

        respond({ form });
        render(group([action('data#0.rename', 'Rename', { hasForm: true })]));

        await openMenu();
        await choose('Rename');

        expect(items()).toHaveLength(0);
        expect(document.querySelector('[role="dialog"]')?.textContent).toContain('Rename');
    });

    it('shows that a slow action is running on the button, because the menu is gone', async () => {
        vi.useFakeTimers();

        let finish: (response: Response) => void;

        vi.mocked(fetch).mockReturnValueOnce(new Promise((resolve) => (finish = resolve)));
        render(group([action('data#0.edit', 'Edit')]));

        await openMenu();
        await choose('Edit');
        await vi.advanceTimersByTimeAsync(LOADING_INDICATOR_DELAY);

        const button = wrapper.find('[data-xq-action-group] button');

        expect(button.attributes('aria-busy')).toBe('true');

        finish!(new Response(JSON.stringify(patch), { status: 200 }));
        await flushPromises();
        await vi.advanceTimersByTimeAsync(0);

        expect(button.attributes('aria-busy')).toBeUndefined();
    });

    it('makes a link of an action with a URL, inside the app or out of it', async () => {
        render(
            group([
                action('data#0.view', 'View', { url: '/products/1' }),
                action('data#0.docs', 'Docs', { url: '/docs', openInNewTab: true }),
            ]),
        );

        await openMenu();

        const [view, docs] = items();

        expect(view.hasAttribute('data-inertia-link')).toBe(true);
        expect(view.getAttribute('href')).toBe('/products/1');
        expect(docs.tagName).toBe('A');
        expect(docs.hasAttribute('data-inertia-link')).toBe(false);
        expect(docs.getAttribute('rel')).toBe('noopener noreferrer');
        expect(vi.mocked(fetch)).not.toHaveBeenCalled();
    });

    it('does not run a disabled action', async () => {
        render(group([action('data#0.edit', 'Edit', { disabled: true })]));

        await openMenu();
        await choose('Edit');

        expect(vi.mocked(fetch)).not.toHaveBeenCalled();
    });
});
