import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import XqForm from '../../resources/js/components/XqForm.vue';
import { formatPhpDate } from '../../resources/js/core/dates';
import { createFormStore } from '../../resources/js/core/store';
import { field, schema } from './fixtures';

describe('formatPhpDate', () => {
    const date = new Date(2026, 2, 4, 15, 6, 7);

    it.each([
        ['d/m/Y', '04/03/2026'],
        ['j n y', '4 3 26'],
        ['H:i:s', '15:06:07'],
        ['g:i A', '3:06 PM'],
        ['h:i a', '03:06 pm'],
        ['D, d M Y', 'Wed, 04 Mar 2026'],
        ['l F', 'Wednesday March'],
        ['\\Y\\e\\a\\r Y', 'Year 2026'],
        ['Y-m-d \\a\\t H:i', '2026-03-04 at 15:06'],
        ['Q', 'Q'],
    ])('formats %s', (format, expected) => {
        expect(formatPhpDate(date, format, 'en-US')).toBe(expected);
    });

    it('shows twelve o clock as 12', () => {
        expect(formatPhpDate(new Date(2026, 0, 1, 0, 5), 'g:i a')).toBe('12:05 am');
        expect(formatPhpDate(new Date(2026, 0, 1, 12, 5), 'h:i A')).toBe('12:05 PM');
    });
});

describe('date and time fields', () => {
    async function render(type: string, props: Record<string, unknown>, value: unknown) {
        const store = createFormStore(schema([field('data.at', type, { props })], { at: value }));
        const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

        await vi.dynamicImportSettled();
        await flushPromises();
        await nextTick();

        return { store, wrapper };
    }

    it('shows a stored value with the display format', async () => {
        const { wrapper } = await render(
            'date-time-picker',
            { time: true, seconds: false, displayFormat: 'd/m/Y H:i' },
            '2026-03-04 05:06',
        );

        expect(wrapper.text()).toContain('04/03/2026 05:06');
        wrapper.unmount();
    });

    it('renders only time segments on a time picker', async () => {
        const { wrapper } = await render(
            'time-picker',
            { time: true, timeOnly: true, seconds: true },
            '05:06:07',
        );

        expect(wrapper.find('[data-slot="xq-time-field"]').exists()).toBe(true);
        expect(wrapper.find('[aria-haspopup="dialog"]').exists()).toBe(false);
        expect(wrapper.text().replace(/\s/g, '')).toContain('05:06:07');
        wrapper.unmount();
    });

    it('keeps the time while a segment is being retyped', async () => {
        const { store, wrapper } = await render(
            'date-time-picker',
            { time: true, seconds: false },
            '2026-03-04 05:06',
        );
        const hour = wrapper.find('[data-reka-time-field-segment="hour"]');

        await hour.trigger('keydown', { key: 'Backspace' });
        await hour.trigger('keydown', { key: 'Backspace' });
        await flushPromises();

        expect(store.get('data.at')).toBe('2026-03-04 05:06');

        await hour.trigger('keydown', { key: '0' });
        await hour.trigger('keydown', { key: '7' });
        await flushPromises();

        expect(store.get('data.at')).toBe('2026-03-04 07:06');
        wrapper.unmount();
    });

    it('still clears a time picker with no date', async () => {
        const { store, wrapper } = await render(
            'time-picker',
            { time: true, timeOnly: true, seconds: false },
            '05:06',
        );
        const hour = wrapper.find('[data-reka-time-field-segment="hour"]');

        await hour.trigger('keydown', { key: 'Backspace' });
        await hour.trigger('keydown', { key: 'Backspace' });
        await flushPromises();

        expect(store.get('data.at')).toBeNull();
        wrapper.unmount();
    });

    it('keeps the date when the time changes', async () => {
        const { store, wrapper } = await render(
            'date-time-picker',
            { time: true, seconds: false },
            '2026-03-04 05:06',
        );
        const segment = wrapper.find('[data-reka-time-field-segment="minute"]');

        await segment.trigger('keydown', { key: 'ArrowUp' });
        await nextTick();

        expect(store.get('data.at')).toBe('2026-03-04 05:07');
        wrapper.unmount();
    });
});
