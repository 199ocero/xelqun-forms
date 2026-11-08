import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import XqForm from '../../resources/js/components/XqForm.vue';
import { createFormStore } from '../../resources/js/core/store';
import { field, schema } from './fixtures';

const pdf = (name: string, bytes = 10) =>
    new File(['x'.repeat(bytes)], name, { type: 'application/pdf' });

async function render(props: Record<string, unknown>, value: unknown) {
    const store = createFormStore(
        schema([field('data.v', 'file-upload', { props })], { v: value }),
    );
    const wrapper = mount(XqForm, { props: { form: store }, attachTo: document.body });

    await vi.dynamicImportSettled();
    await flushPromises();

    const pick = async (...files: File[]) => {
        const input = wrapper.find<HTMLInputElement>('input[type="file"]');

        Object.defineProperty(input.element, 'files', { value: files, configurable: true });
        await input.trigger('change');
        await flushPromises();
    };

    return { store, wrapper, pick };
}

function controlled(store: any) {
    const calls: Array<{
        name: string;
        finish: (token: string) => void;
        fail: () => void;
        signal?: AbortSignal;
    }> = [];

    vi.spyOn(store, 'upload').mockImplementation(
        (_path: any, file: any, _progress: any, signal: any) => {
            return new Promise((resolve, reject) => {
                calls.push({
                    name: file.name,
                    signal,
                    finish: (token) =>
                        resolve({ token, name: file.name, size: file.size, mime: null } as any),
                    fail: () => reject(new Error('Failed')),
                });
            });
        },
    );

    return calls;
}

afterEach(() => {
    vi.restoreAllMocks();
    document.body.innerHTML = '';
});

describe('several files', () => {
    it('keeps the order they were picked in, whichever finishes first', async () => {
        const { store, wrapper, pick } = await render(
            { multiple: true, maxParallelUploads: 2 },
            [],
        );
        const calls = controlled(store);

        await pick(pdf('a.pdf'), pdf('b.pdf'));

        calls[1].finish('tok-b');
        await flushPromises();
        expect(store.get('data.v')).toEqual([]);

        calls[0].finish('tok-a');
        await flushPromises();
        expect(store.get('data.v')).toEqual(['tok-a', 'tok-b']);
        wrapper.unmount();
    });

    it('uploads one at a time unless told otherwise', async () => {
        const { store, wrapper, pick } = await render({ multiple: true }, []);
        const calls = controlled(store);

        await pick(pdf('a.pdf'), pdf('b.pdf'));
        expect(calls).toHaveLength(1);

        calls[0].finish('tok-a');
        await flushPromises();
        expect(calls).toHaveLength(2);
        wrapper.unmount();
    });

    it('runs no more than the limit at once', async () => {
        const { store, wrapper, pick } = await render(
            { multiple: true, maxParallelUploads: 2 },
            [],
        );
        const calls = controlled(store);

        await pick(pdf('a.pdf'), pdf('b.pdf'), pdf('c.pdf'), pdf('d.pdf'));
        expect(calls).toHaveLength(2);

        calls[0].finish('tok-a');
        await flushPromises();
        expect(calls).toHaveLength(3);

        await pick(pdf('e.pdf'));
        expect(calls).toHaveLength(3);

        calls[1].finish('tok-b');
        calls[2].finish('tok-c');
        await flushPromises();
        calls[3].finish('tok-d');
        await flushPromises();
        calls[4].finish('tok-e');
        await flushPromises();

        expect(store.get('data.v')).toEqual(['tok-a', 'tok-b', 'tok-c', 'tok-d', 'tok-e']);
        wrapper.unmount();
    });

    it('makes the form wait for files that have not started yet', async () => {
        const { store, wrapper, pick } = await render(
            { multiple: true, maxParallelUploads: 1 },
            [],
        );
        const calls = controlled(store);

        await pick(pdf('a.pdf'), pdf('b.pdf'));

        expect(store.uploading.value).toBeGreaterThan(0);

        calls[0].finish('tok-a');
        await flushPromises();

        expect(store.uploading.value).toBeGreaterThan(0);

        calls[1].finish('tok-b');
        await flushPromises();

        expect(store.uploading.value).toBe(0);
        wrapper.unmount();
    });

    it('carries on after one file fails, and says so', async () => {
        const { store, wrapper, pick } = await render(
            { multiple: true, maxParallelUploads: 1 },
            [],
        );
        const calls = controlled(store);

        await pick(pdf('a.pdf'), pdf('b.pdf'));

        calls[0].fail();
        await flushPromises();
        calls[1].finish('tok-b');
        await flushPromises();

        expect(store.get('data.v')).toEqual(['tok-b']);
        expect(wrapper.find('[role="alert"]').text()).toContain('upload failed');
        wrapper.unmount();
    });

    it('stops at maxFiles, in the browser too', async () => {
        const { store, wrapper, pick } = await render(
            { multiple: true, maxFiles: 3, maxParallelUploads: 2 },
            ['old.pdf'],
        );
        const calls = controlled(store);

        await pick(pdf('a.pdf'), pdf('b.pdf'), pdf('c.pdf'));

        expect(calls.map(({ name }) => name)).toEqual(['a.pdf', 'b.pdf']);
        expect(wrapper.find('[role="alert"]').text()).toContain('2 more files');
        calls[0].finish('tok-a');
        calls[1].finish('tok-b');
        await flushPromises();

        expect(wrapper.find('input[type="file"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('checks sizes before uploading', async () => {
        const { store, wrapper, pick } = await render(
            { multiple: true, minSize: 1, maxSize: 2 },
            [],
        );
        const calls = controlled(store);

        await pick(pdf('small.pdf', 10));
        await pick(pdf('big.pdf', 4096));
        await pick(pdf('fine.pdf', 1500));

        expect(calls.map(({ name }) => name)).toEqual(['fine.pdf']);
        wrapper.unmount();
    });

    it('cancels one upload without touching the others', async () => {
        const { store, wrapper, pick } = await render(
            { multiple: true, maxParallelUploads: 2 },
            [],
        );
        const calls = controlled(store);

        await pick(pdf('a.pdf'), pdf('b.pdf'));
        await wrapper.find('[aria-label="Cancel a.pdf"]').trigger('click');

        expect(calls[0].signal?.aborted).toBe(true);
        expect(calls[1].signal?.aborted).toBe(false);

        calls[1].finish('tok-b');
        await flushPromises();

        expect(store.get('data.v')).toEqual(['tok-b']);
        wrapper.unmount();
    });

    it('removes one file and moves another with its buttons', async () => {
        const { store, wrapper } = await render({ multiple: true, reorderable: true }, [
            'a.pdf',
            'b.pdf',
            'c.pdf',
        ]);

        await wrapper.find('[aria-label="Move b.pdf up"]').trigger('click');
        expect(store.get('data.v')).toEqual(['b.pdf', 'a.pdf', 'c.pdf']);

        await wrapper.find('[aria-label="Remove a.pdf"]').trigger('click');
        expect(store.get('data.v')).toEqual(['b.pdf', 'c.pdf']);
        wrapper.unmount();
    });

    it('keeps the keyboard in place after a move or a removal', async () => {
        const { wrapper } = await render({ multiple: true, reorderable: true }, [
            'a.pdf',
            'b.pdf',
            'c.pdf',
        ]);
        const focused = () => document.activeElement?.getAttribute('aria-label');

        await wrapper.find('[aria-label="Move b.pdf up"]').trigger('click');
        await nextTick();
        expect(focused()).toBe('Move b.pdf down');

        await wrapper.find('[aria-label="Move a.pdf down"]').trigger('click');
        await nextTick();
        expect(focused()).toBe('Move a.pdf up');

        await wrapper.find('[aria-label="Move a.pdf up"]').trigger('click');
        await nextTick();
        expect(focused()).toBe('Move a.pdf up');

        await wrapper.find('[aria-label="Remove a.pdf"]').trigger('click');
        await nextTick();
        expect(focused()).toBe('Remove c.pdf');

        await wrapper.find('[aria-label="Remove c.pdf"]').trigger('click');
        await nextTick();
        expect(focused()).toBe('Remove b.pdf');

        await wrapper.find('[aria-label="Remove b.pdf"]').trigger('click');
        await nextTick();
        expect(document.activeElement?.getAttribute('type')).toBe('file');
        wrapper.unmount();
    });

    it('puts the newest file first with appendFiles off', async () => {
        const { store, wrapper, pick } = await render({ multiple: true, appendFiles: false }, [
            'old.pdf',
        ]);
        const calls = controlled(store);

        await pick(pdf('a.pdf'));
        calls[0].finish('tok-a');
        await flushPromises();
        await pick(pdf('b.pdf'));
        calls[1].finish('tok-b');
        await flushPromises();

        expect(store.get('data.v')).toEqual(['tok-b', 'tok-a', 'old.pdf']);
        wrapper.unmount();
    });

    it('hides the remove button of stored files only', async () => {
        const { store, wrapper, pick } = await render({ multiple: true, deletable: false }, [
            'old.pdf',
        ]);
        const calls = controlled(store);

        await pick(pdf('new.pdf'));
        calls[0].finish('tok-new');
        await flushPromises();

        expect(wrapper.find('[aria-label="Remove old.pdf"]').exists()).toBe(false);
        expect(wrapper.find('[aria-label="Remove new.pdf"]').exists()).toBe(true);
        wrapper.unmount();
    });

    it.each([
        ['a list', { multiple: true }, ['a.png']],
        ['an avatar', { avatar: true, image: true }, 'a.png'],
    ])('shows no thumbnail in %s with previewable off', async (_label, props, value) => {
        const files = { 'a.png': { name: 'a.png', url: '/a.png', size: null } };
        const { wrapper } = await render({ ...props, files }, value);

        expect(wrapper.find('img').exists()).toBe(true);
        wrapper.unmount();

        const { wrapper: plain } = await render({ ...props, files, previewable: false }, value);

        expect(plain.find('img').exists()).toBe(false);
        plain.unmount();
    });

    it('offers to pick several at once', async () => {
        const { wrapper } = await render({ multiple: true }, []);

        expect(wrapper.find('input[type="file"]').attributes('multiple')).toBeDefined();
        wrapper.unmount();
    });

    it('survives a cloned row holding null', async () => {
        const { store, wrapper, pick } = await render({ multiple: true }, null);
        const calls = controlled(store);

        await pick(pdf('a.pdf'));
        calls[0].finish('tok-a');
        await flushPromises();

        expect(store.get('data.v')).toEqual(['tok-a']);
        wrapper.unmount();
    });
});

describe('one file', () => {
    it('replaces a running upload with a newer pick', async () => {
        const { store, wrapper, pick } = await render({}, null);
        const calls = controlled(store);

        await pick(pdf('a.pdf'));
        const input = wrapper.findAll('input[type="file"]');

        expect(input).toHaveLength(0);
        expect(calls).toHaveLength(1);

        calls[0].finish('tok-a');
        await flushPromises();

        expect(store.get('data.v')).toBe('tok-a');
        wrapper.unmount();
    });

    it('stays a plain value and hides the dropzone', async () => {
        const { store, wrapper } = await render({}, 'a.pdf');

        expect(wrapper.find('input[type="file"]').exists()).toBe(false);

        await wrapper.find('[aria-label="Remove file"]').trigger('click');

        expect(store.get('data.v')).toBeNull();
        expect(wrapper.find('input[type="file"]').exists()).toBe(true);
        wrapper.unmount();
    });
});

describe('pasting', () => {
    function paste(wrapper: any, files: File[]) {
        const event = new Event('paste', { bubbles: true, cancelable: true }) as any;

        event.clipboardData = { files };
        wrapper.find('input[type="file"]').element.dispatchEvent(event);
    }

    /** A full field has no input, but its buttons still take focus. */
    function pasteOnFile(wrapper: any, files: File[]) {
        const event = new Event('paste', { bubbles: true, cancelable: true }) as any;

        event.clipboardData = { files };
        wrapper.find('[data-xq-file]').element.dispatchEvent(event);
    }

    it('takes pasted files while it has focus', async () => {
        const { store, wrapper } = await render({ multiple: true }, []);
        const calls = controlled(store);

        paste(wrapper, [pdf('pasted.pdf')]);
        await nextTick();

        expect(calls.map(({ name }) => name)).toEqual(['pasted.pdf']);
        wrapper.unmount();
    });

    it('ignores them when told to', async () => {
        const { store, wrapper } = await render({ multiple: true, pasteable: false }, []);
        const calls = controlled(store);

        paste(wrapper, [pdf('pasted.pdf')]);
        await nextTick();

        expect(calls).toHaveLength(0);
        wrapper.unmount();
    });

    it.each([
        ['a full list', { multiple: true, maxFiles: 1 }, ['old.pdf']],
        ['a list over its limit', { multiple: true, maxFiles: 2 }, ['1.pdf', '2.pdf', '3.pdf']],
        ['a single file field that has its file', {}, 'old.pdf'],
    ])('adds nothing to %s', async (_label, props, value) => {
        const { store, wrapper } = await render(props, value);
        const calls = controlled(store);

        pasteOnFile(wrapper, [pdf('a.pdf'), pdf('b.pdf'), pdf('c.pdf')]);
        await flushPromises();

        expect(calls).toHaveLength(0);
        expect(wrapper.text()).not.toContain('more file');
        wrapper.unmount();
    });
});

describe('stored files', () => {
    const files = { 'x/a.pdf': { name: 'a.pdf', url: '/storage/x/a.pdf', size: null } };

    it('offers open and download only when asked', async () => {
        const plain = await render({ multiple: true, files }, ['x/a.pdf']);

        expect(plain.wrapper.find('a').exists()).toBe(false);
        plain.wrapper.unmount();

        const { wrapper } = await render(
            { multiple: true, files, openable: true, downloadable: true },
            ['x/a.pdf'],
        );
        const open = wrapper.find('[aria-label="Open a.pdf"]');
        const download = wrapper.find('[aria-label="Download a.pdf"]');

        expect(open.attributes('href')).toBe('/storage/x/a.pdf');
        expect(open.attributes('rel')).toBe('noopener');
        expect(download.attributes('download')).toBe('a.pdf');
        wrapper.unmount();
    });

    it('offers neither for a file with no link', async () => {
        const { wrapper } = await render(
            {
                multiple: true,
                files: { 'x/a.pdf': { name: 'a.pdf', url: null, size: null } },
                openable: true,
            },
            ['x/a.pdf'],
        );

        expect(wrapper.find('[aria-label="Open a.pdf"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('shows an avatar as a named image', async () => {
        const { wrapper } = await render(
            {
                avatar: true,
                image: true,
                files: { 'x/me.png': { name: 'me.png', url: '/me.png', size: null } },
            },
            'x/me.png',
        );

        expect(wrapper.find('img').attributes('alt')).toBe('me.png');
        expect(wrapper.find('img').classes()).toContain('rounded-full');
        wrapper.unmount();
    });
});

describe('a busy server', () => {
    afterEach(() => vi.useRealTimers());

    it('waits as long as a 429 asks and tries the file again', async () => {
        const { store, pick } = await render({ multiple: true }, []);
        const { HttpError } = await import('../../resources/js/core/http');
        const upload = vi
            .spyOn(store, 'upload')
            .mockRejectedValueOnce(new HttpError(429, { message: 'Too Many Attempts.' }, 2))
            .mockResolvedValueOnce({ token: 't1', name: 'a.pdf', size: 10, mime: null } as any);

        vi.useFakeTimers();
        await pick(pdf('a.pdf'));
        await vi.advanceTimersByTimeAsync(1999);
        expect(upload).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(1);
        await flushPromises();
        expect(upload).toHaveBeenCalledTimes(2);
        expect(store.get('data.v')).toEqual(['t1']);
        expect(document.body.textContent).not.toContain('Too Many Attempts');
    });
});
