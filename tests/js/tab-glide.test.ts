import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import XqTabGlide from '../../resources/js/ui/XqTabGlide.vue';

function mountTabs(activeId: string | null = 'one') {
    const wrapper = mount(
        defineComponent({
            setup: () => () =>
                h('div', [
                    h(XqTabGlide),
                    ...['one', 'two', 'three'].map((id) =>
                        h('button', {
                            id,
                            disabled: id === 'three',
                            'aria-selected': id === activeId ? 'true' : 'false',
                        }),
                    ),
                ]),
        }),
        { attachTo: document.body },
    );

    wrapper.findAll('button').forEach((button, index) => {
        Object.defineProperty(button.element, 'offsetLeft', { value: index * 100 });
        Object.defineProperty(button.element, 'offsetWidth', { value: 80 + index });
    });

    return wrapper;
}

const tab = (wrapper: ReturnType<typeof mountTabs>, id: string) => wrapper.find(`#${id}`);
const glide = (wrapper: ReturnType<typeof mountTabs>) => wrapper.find('[data-xq-tab-glide]');
const list = (wrapper: ReturnType<typeof mountTabs>) => glide(wrapper).element.parentElement!;
const position = (wrapper: ReturnType<typeof mountTabs>) =>
    (glide(wrapper).element as HTMLElement).style.transform;

async function settleOnActive(wrapper: ReturnType<typeof mountTabs>): Promise<void> {
    list(wrapper).dispatchEvent(new Event('pointerleave'));
    await flushPromises();
}

describe('XqTabGlide', () => {
    it('leans over to a hovered tab, sliding there, and settles back', async () => {
        const wrapper = mountTabs();

        await settleOnActive(wrapper);
        expect(position(wrapper)).toBe('translateX(0px)');

        await tab(wrapper, 'two').trigger('pointerover');
        expect(position(wrapper)).toBe('translateX(100px)');
        expect(glide(wrapper).attributes('style')).toContain('width: 81px');
        expect(glide(wrapper).classes().join(' ')).toContain('[transform');

        await settleOnActive(wrapper);
        expect(position(wrapper)).toBe('translateX(0px)');
    });

    it('follows keyboard focus', async () => {
        const wrapper = mountTabs();

        await tab(wrapper, 'two').trigger('focusin');
        expect(position(wrapper)).toBe('translateX(100px)');

        await tab(wrapper, 'two').trigger('focusout');
        expect(position(wrapper)).toBe('translateX(0px)');
    });

    it('moves when the current tab changes', async () => {
        const wrapper = mountTabs();

        tab(wrapper, 'one').element.setAttribute('aria-selected', 'false');
        tab(wrapper, 'two').element.setAttribute('aria-selected', 'true');
        await flushPromises();

        expect(position(wrapper)).toBe('translateX(100px)');
    });

    it('stays hidden with no current tab, and shows while one is hovered', async () => {
        const wrapper = mountTabs(null);

        expect(glide(wrapper).attributes('style')).toContain('opacity: 0');

        await tab(wrapper, 'two').trigger('pointerover');
        expect(glide(wrapper).attributes('style')).toContain('opacity: 1');
        expect(glide(wrapper).classes().join(' ')).not.toContain('[transform');

        await settleOnActive(wrapper);
        expect(glide(wrapper).attributes('style')).toContain('opacity: 0');
    });

    it('ignores what sits between tabs', async () => {
        const wrapper = mountTabs();

        await tab(wrapper, 'two').trigger('pointerover');
        list(wrapper).dispatchEvent(new Event('pointerover', { bubbles: true }));
        await flushPromises();

        expect(position(wrapper)).toBe('translateX(100px)');
    });

    it('is hidden from screen readers', () => {
        expect(glide(mountTabs()).attributes('aria-hidden')).toBe('true');
    });
});
