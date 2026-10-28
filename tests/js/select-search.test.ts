import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import { field, schema } from './fixtures';

function setup() {
    const store = createFormStore(
        schema([field('data.country', 'select', { props: { options: [], searchable: true, async: true, searchDebounce: 250 } })], {
            country: null,
        }),
    );
    const search = vi
        .spyOn(store, 'search')
        .mockImplementation(async (_path, term) => [{ value: 1, label: `${term} result` }]);
    const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });
    const input = wrapper.find<HTMLInputElement>('input');

    async function typeText(text: string): Promise<void> {
        input.element.value = text;
        await input.trigger('input');
    }

    return { store, search, wrapper, typeText };
}

describe('server-searched select', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('waits for a pause in typing and sends one search', async () => {
        const { search, typeText, wrapper } = setup();

        await typeText('P');
        await vi.advanceTimersByTimeAsync(100);
        await typeText('Ph');
        await vi.advanceTimersByTimeAsync(100);
        await typeText('Phi');
        await vi.advanceTimersByTimeAsync(249);

        expect(search).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);

        expect(search).toHaveBeenCalledTimes(1);
        expect(search).toHaveBeenCalledWith('data.country', 'Phi', expect.any(AbortSignal));
        wrapper.unmount();
    });

    it('never searches for empty text or the term it just searched', async () => {
        const { search, typeText, wrapper } = setup();

        await typeText('Phi');
        await vi.advanceTimersByTimeAsync(250);
        await typeText('Phi ');
        await typeText('   ');
        await vi.advanceTimersByTimeAsync(1000);

        expect(search).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });

    it('reuses cached results for a term it already searched', async () => {
        const { search, typeText, wrapper } = setup();

        await typeText('Japan');
        await vi.advanceTimersByTimeAsync(250);
        await typeText('Ja');
        await vi.advanceTimersByTimeAsync(250);
        await typeText('Japan');
        await vi.advanceTimersByTimeAsync(1000);

        expect(search.mock.calls.map(([, term]) => term)).toEqual(['Japan', 'Ja']);
        wrapper.unmount();
    });

    it('does not search when the value changes without typing', async () => {
        const { store, search, wrapper } = setup();

        store.set('data.country', 1);
        await vi.advanceTimersByTimeAsync(1000);
        store.set('data.country', null);
        await vi.advanceTimersByTimeAsync(1000);

        expect(search).not.toHaveBeenCalled();
        wrapper.unmount();
    });
});
