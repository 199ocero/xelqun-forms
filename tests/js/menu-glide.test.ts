import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import XqMenuGlide from '../../resources/js/ui/XqMenuGlide.vue';

const List = defineComponent({
    setup: () => () =>
        h('div', { style: 'position: relative' }, [
            h(XqMenuGlide),
            h('button', { id: 'one' }),
            h('button', { id: 'two' }),
        ]),
});

describe('XqMenuGlide', () => {
    const glide = (wrapper: ReturnType<typeof mount>) => wrapper.find('[data-xq-menu-glide]');

    async function highlight(wrapper: ReturnType<typeof mount>, id: string | null): Promise<void> {
        wrapper.findAll('button').forEach((button) => {
            button.element.toggleAttribute('data-highlighted', button.attributes('id') === id);
        });
        await flushPromises();
    }

    const settle = async (): Promise<void> => {
        await new Promise((resolve) => setTimeout(resolve, 80));
        await flushPromises();
    };

    afterEach(() => (document.body.innerHTML = ''));

    it('stays hidden with nothing highlighted', () => {
        expect(glide(mount(List)).attributes('style')).toContain('opacity: 0');
    });

    it('appears on the first item, then slides to the next', async () => {
        const wrapper = mount(List);

        await highlight(wrapper, 'one');
        expect(glide(wrapper).attributes('style')).toContain('opacity: 1');
        expect(glide(wrapper).classes().join(' ')).not.toContain('[transform');

        await highlight(wrapper, 'two');
        expect(glide(wrapper).classes().join(' ')).toContain('[transform');
    });

    it('fades out when nothing is highlighted, and starts fresh next time', async () => {
        const wrapper = mount(List);

        await highlight(wrapper, 'one');
        await highlight(wrapper, 'two');
        await highlight(wrapper, null);
        await settle();
        expect(glide(wrapper).attributes('style')).toContain('opacity: 0');

        await highlight(wrapper, 'one');
        expect(glide(wrapper).classes().join(' ')).not.toContain('[transform');
    });

    it('keeps sliding when the highlight drops for a moment between items', async () => {
        const wrapper = mount(List);

        await highlight(wrapper, 'one');
        await highlight(wrapper, null);
        await highlight(wrapper, 'two');
        await settle();

        expect(glide(wrapper).attributes('style')).toContain('opacity: 1');
        expect(glide(wrapper).classes().join(' ')).toContain('[transform');
    });

    it('finds an item already highlighted when the list opens', async () => {
        const wrapper = mount(
            defineComponent({
                setup: () => () =>
                    h('div', [h(XqMenuGlide), h('button', { 'data-highlighted': '' })]),
            }),
        );

        await flushPromises();

        expect(glide(wrapper).attributes('style')).toContain('opacity: 1');
    });

    describe('with hover items', () => {
        function mountCards() {
            return mount(
                defineComponent({
                    setup: () => () =>
                        h('div', [
                            h(XqMenuGlide, { hoverItems: 'a', class: 'bg-xq-primary' }),
                            h('a', { id: 'one' }, [h('span', { id: 'label' })]),
                            h('a', { id: 'two' }),
                            h('p', { id: 'plain' }),
                        ]),
                }),
            );
        }

        it('slides from card to card, starting from what is inside one', async () => {
            const wrapper = mountCards();

            await wrapper.find('#label').trigger('pointerover');
            expect(glide(wrapper).attributes('style')).toContain('opacity: 1');
            expect(glide(wrapper).classes().join(' ')).not.toContain('[transform');

            await wrapper.find('#two').trigger('pointerover');
            expect(glide(wrapper).classes().join(' ')).toContain('[transform');
        });

        it('holds on across the gap between cards', async () => {
            const wrapper = mountCards();

            await wrapper.find('#one').trigger('pointerover');
            await wrapper.trigger('pointerover');
            await settle();

            expect(glide(wrapper).attributes('style')).toContain('opacity: 1');
        });

        it('fades on a card that goes nowhere, and when the pointer leaves', async () => {
            const wrapper = mountCards();

            await wrapper.find('#one').trigger('pointerover');
            await wrapper.find('#plain').trigger('pointerover');
            await settle();
            expect(glide(wrapper).attributes('style')).toContain('opacity: 0');

            await wrapper.find('#two').trigger('focusin');
            expect(glide(wrapper).attributes('style')).toContain('opacity: 1');

            wrapper.element.dispatchEvent(new Event('pointerleave'));
            await settle();
            expect(glide(wrapper).attributes('style')).toContain('opacity: 0');
        });

        it('takes a different color', () => {
            expect(glide(mountCards()).classes()).toContain('bg-xq-primary');
            expect(glide(mountCards()).classes()).not.toContain('bg-xq-accent');
        });
    });

    it('has a color of its own by default', () => {
        expect(glide(mount(List)).classes()).toContain('bg-xq-accent');
    });

    it('stays out of the way of screen readers and clicks', () => {
        const wrapper = mount(List);

        expect(glide(wrapper).attributes('aria-hidden')).toBe('true');
        expect(glide(wrapper).classes()).toContain('pointer-events-none');
    });
});
