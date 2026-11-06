import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import XqFileUploadField from '../../resources/js/components/fields/XqFileUploadField.vue';
import { FORM_STORE } from '../../resources/js/composables/context';
import { computeFrame, prepareImage } from '../../resources/js/core/images';
import { createFormStore } from '../../resources/js/core/store';
import { field, schema } from './fixtures';

vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn() } }));

const cropper = vi.hoisted(() => ({
    rotate: vi.fn(),
    scaleX: vi.fn(),
    scaleY: vi.fn(),
    reset: vi.fn(),
    setAspectRatio: vi.fn(),
    destroy: vi.fn(),
    getCroppedCanvas: vi.fn(() => ({
        toBlob: (done: (blob: Blob) => void) => done(new Blob(['edited'], { type: 'image/png' })),
    })),
}));

vi.mock('cropperjs', () => ({
    default: vi.fn(function (this: unknown) {
        return cropper;
    }),
}));

vi.mock('cropperjs/dist/cropper.css', () => ({}));

describe('computeFrame', () => {
    it('leaves an image alone with nothing asked', () => {
        expect(computeFrame(800, 600, {})).toEqual({
            crop: { x: 0, y: 0, width: 800, height: 600 },
            width: 800,
            height: 600,
        });
    });

    it('crops the middle to a shape', () => {
        expect(computeFrame(800, 600, { ratio: '1:1' })).toEqual({
            crop: { x: 100, y: 0, width: 600, height: 600 },
            width: 600,
            height: 600,
        });
    });

    it('crops a tall image from the top and bottom', () => {
        expect(computeFrame(600, 800, { ratio: '1:1' }).crop).toEqual({
            x: 0,
            y: 100,
            width: 600,
            height: 600,
        });
    });

    it('works out the side it was not given', () => {
        expect(computeFrame(800, 600, { width: 400, mode: 'contain' })).toMatchObject({
            width: 400,
            height: 300,
        });
    });

    it('fits inside the box with contain', () => {
        expect(computeFrame(800, 600, { width: 100, height: 100, mode: 'contain' })).toMatchObject({
            width: 100,
            height: 75,
        });
    });

    it('stretches to the box with force', () => {
        expect(computeFrame(800, 600, { width: 100, height: 100, mode: 'force' })).toMatchObject({
            crop: { x: 0, y: 0, width: 800, height: 600 },
            width: 100,
            height: 100,
        });
    });

    it('fills the box with cover and crops what is left', () => {
        expect(computeFrame(800, 600, { width: 100, height: 100, mode: 'cover' })).toEqual({
            crop: { x: 100, y: 0, width: 600, height: 600 },
            width: 100,
            height: 100,
        });
    });

    it('keeps the crop in the middle of the source with both a shape and cover', () => {
        const { crop } = computeFrame(1000, 400, {
            ratio: '2:1',
            width: 100,
            height: 100,
            mode: 'cover',
        });

        expect(crop.x).toBeCloseTo(300);
        expect(crop.width).toBeCloseTo(400);
        expect(crop.y).toBeCloseTo(0);
    });

    it('does not enlarge when told not to', () => {
        expect(
            computeFrame(100, 100, { width: 400, height: 400, mode: 'contain', upscale: false }),
        ).toMatchObject({
            width: 100,
            height: 100,
        });
    });

    it('ignores a shape it can not read', () => {
        expect(computeFrame(800, 600, { ratio: 'wide' }).width).toBe(800);
    });
});

describe('prepareImage', () => {
    it('sends a file as it is with nothing asked', async () => {
        const file = new File(['x'], 'a.png', { type: 'image/png' });

        expect(await prepareImage(file, null)).toBe(file);
    });

    it('leaves a GIF alone, which would lose its frames', async () => {
        const file = new File(['x'], 'a.gif', { type: 'image/gif' });

        expect(await prepareImage(file, { width: 100 })).toBe(file);
    });
});

describe('an image the browser can not read', () => {
    it('goes up as it is, so the server can give the reason', async () => {
        vi.stubGlobal(
            'createImageBitmap',
            vi.fn(() => Promise.reject(new Error('corrupt'))),
        );

        const file = new File(['x'], 'a.png', { type: 'image/png' });

        expect(await prepareImage(file, { width: 100 })).toBe(file);
        vi.unstubAllGlobals();
    });
});

describe('image editor', () => {
    const stored = { 'a.png': { name: 'a.png', url: '/storage/a.png', size: null } };
    const editor = { aspectRatios: [null, '1:1'], fillColor: '#fff' };

    beforeEach(() => {
        URL.createObjectURL = vi.fn(() => 'blob:edited');
        URL.revokeObjectURL = vi.fn();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    async function mountUpload(props: Record<string, unknown>, value: unknown = 'a.png') {
        const store = createFormStore(
            schema(
                [
                    field('data.photo', 'file-upload', {
                        props: { image: true, files: stored, ...props },
                    }),
                ],
                { photo: value },
            ),
        );
        const wrapper = mount(XqFileUploadField, {
            props: { node: store.nodes.get('data.photo')! },
            global: { provide: { [FORM_STORE as symbol]: store } },
            attachTo: document.body,
        });

        await flushPromises();

        return { store, wrapper };
    }

    async function openEditor(wrapper: any) {
        await wrapper.find('[data-xq-edit-image]').trigger('click');
        await vi.waitFor(() =>
            expect(document.body.querySelector('[role="dialog"] img')).not.toBeNull(),
        );
        document.body.querySelector('[role="dialog"] img')!.dispatchEvent(new Event('load'));
        await flushPromises();
    }

    it('offers the editor on an avatar too', async () => {
        const { wrapper } = await mountUpload({ imageEditor: editor, avatar: true });

        expect(wrapper.find('[data-xq-edit-image]').exists()).toBe(true);
        wrapper.unmount();
    });

    it('offers no button unless the field asks', async () => {
        const { wrapper } = await mountUpload({});

        expect(wrapper.find('[data-xq-edit-image]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('leaves a stored file alone if it may not be replaced', async () => {
        const { wrapper } = await mountUpload({ imageEditor: editor, deletable: false });

        expect(wrapper.find('[data-xq-edit-image]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('uploads the edited image in the place of the old one', async () => {
        const { store, wrapper } = await mountUpload({ imageEditor: editor, multiple: true }, [
            'a.png',
            'b.png',
        ]);
        const upload = vi
            .spyOn(store, 'upload')
            .mockResolvedValue({ token: 'xq-tmp:new', name: 'a.png', size: 6, mime: 'image/png' });

        await openEditor(wrapper);
        document.body.querySelector<HTMLButtonElement>('[data-xq-image-save]')!.click();
        await flushPromises();

        expect(cropper.getCroppedCanvas).toHaveBeenCalledWith({ fillColor: '#fff' });
        expect(upload.mock.calls[0][1].name).toBe('a.png');
        expect(store.get('data.photo')).toEqual(['xq-tmp:new', 'b.png']);
        wrapper.unmount();
    });

    it('keeps the old image when the upload fails', async () => {
        const { store, wrapper } = await mountUpload({ imageEditor: editor });

        vi.spyOn(store, 'upload').mockRejectedValue(new Error('offline'));

        await openEditor(wrapper);
        document.body.querySelector<HTMLButtonElement>('[data-xq-image-save]')!.click();
        await flushPromises();

        expect(store.get('data.photo')).toBe('a.png');
        expect(wrapper.text()).toContain('The upload failed.');
        wrapper.unmount();
    });

    it('changes nothing when cancelled', async () => {
        const { store, wrapper } = await mountUpload({ imageEditor: editor });
        const upload = vi.spyOn(store, 'upload');

        await openEditor(wrapper);
        [...document.body.querySelectorAll<HTMLButtonElement>('[role="dialog"] button')]
            .find((button) => button.textContent?.includes('Cancel'))!
            .click();
        await flushPromises();

        expect(upload).not.toHaveBeenCalled();
        expect(store.get('data.photo')).toBe('a.png');
        expect(cropper.destroy).toHaveBeenCalled();
        wrapper.unmount();
    });

    it('sets the shape the user picks, and free for none', async () => {
        const { wrapper } = await mountUpload({ imageEditor: editor });

        await openEditor(wrapper);
        document.body.querySelector<HTMLButtonElement>('[data-xq-ratio="1:1"]')!.click();
        document.body.querySelector<HTMLButtonElement>('[data-xq-ratio="free"]')!.click();

        expect(cropper.setAspectRatio.mock.calls[0][0]).toBe(1);
        expect(cropper.setAspectRatio.mock.calls[1][0]).toBeNaN();
        wrapper.unmount();
    });
});
