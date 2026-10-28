import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import { schema } from './fixtures';

/**
 * Render every schema snapshot produced by tests/Feature/SchemaSnapshotTest.php,
 * so a PHP contract change that the renderer cannot handle fails here too.
 */
const snapshots = import.meta.glob<string>('../.pest/snapshots/Feature/SchemaSnapshotTest/*.snap', {
    query: '?raw',
    import: 'default',
    eager: true,
});

const cases = Object.entries(snapshots).map(([file, contents]) => [
    file.split('data_set__dataset__')[1]?.replace(/__\.snap$/, '') ?? file,
    JSON.parse(contents),
]);

describe('PHP schema snapshots', () => {
    it('finds the snapshots', () => {
        expect(cases.length).toBeGreaterThan(15);
    });

    it.each(cases)('renders %s', async (name, snapshot) => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const store = createFormStore({
            ...schema(snapshot.components, snapshot.state.data),
            state: snapshot.state,
        });
        const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

        await new Promise((resolve) => setTimeout(resolve));

        const unknown = wrapper.text().includes('No component is registered');

        expect(unknown).toBe(name === 'custom_field');
        expect(warn).not.toHaveBeenCalled();

        wrapper.unmount();
        warn.mockRestore();
    });
});
