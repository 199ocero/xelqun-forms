export interface ImageResize {
    ratio?: string | null;
    mode?: 'force' | 'cover' | 'contain' | null;
    width?: number | null;
    height?: number | null;
    upscale?: boolean;
}

export interface ImageFrame {
    /** The part of the source to draw. */
    crop: { x: number; y: number; width: number; height: number };
    width: number;
    height: number;
}

function parseRatio(ratio: string | null | undefined): number | null {
    const [width, height] = (ratio ?? '').split(':').map(Number);

    return width > 0 && height > 0 ? width / height : null;
}

/** The middle of the source at this shape. */
function centerCrop(sourceWidth: number, sourceHeight: number, ratio: number): ImageFrame['crop'] {
    const width = Math.min(sourceWidth, sourceHeight * ratio);
    const height = width / ratio;

    return {
        x: (sourceWidth - width) / 2,
        y: (sourceHeight - height) / 2,
        width,
        height,
    };
}

/**
 * What to draw, and at what size. Works on numbers alone, so the rules can be
 * tested without a canvas.
 */
export function computeFrame(
    sourceWidth: number,
    sourceHeight: number,
    { ratio, mode, width, height, upscale = true }: ImageResize,
): ImageFrame {
    const shape = parseRatio(ratio);
    let crop = shape
        ? centerCrop(sourceWidth, sourceHeight, shape)
        : { x: 0, y: 0, width: sourceWidth, height: sourceHeight };

    if (!width && !height) {
        return { crop, width: Math.round(crop.width), height: Math.round(crop.height) };
    }

    const aspect = crop.width / crop.height;
    const boxWidth = width ?? height! * aspect;
    const boxHeight = height ?? width! / aspect;

    if (mode === 'cover') {
        const inner = centerCrop(crop.width, crop.height, boxWidth / boxHeight);

        crop = { ...inner, x: crop.x + inner.x, y: crop.y + inner.y };
    }

    if (mode === 'force' || mode === 'cover') {
        const outputWidth = upscale ? boxWidth : Math.min(boxWidth, crop.width);
        const outputHeight = upscale ? boxHeight : Math.min(boxHeight, crop.height);

        return { crop, width: Math.round(outputWidth), height: Math.round(outputHeight) };
    }

    const fit = Math.min(boxWidth / crop.width, boxHeight / crop.height);
    const scale = upscale ? fit : Math.min(fit, 1);

    return { crop, width: Math.round(crop.width * scale), height: Math.round(crop.height * scale) };
}

/**
 * Crop and resize a picked image in the browser, before it uploads. Anything
 * this can't redraw goes up as it is: an animated GIF would lose its frames.
 */
export async function prepareImage(
    file: File,
    resize: ImageResize | null | undefined,
): Promise<File> {
    if (!resize || !['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
        return file;
    }

    // Sent as it is, a file the browser can't read gets a proper reason from the server.
    const bitmap = await createImageBitmap(file).catch(() => null);

    if (!bitmap) {
        return file;
    }

    const { crop, width, height } = computeFrame(bitmap.width, bitmap.height, resize);
    const canvas = document.createElement('canvas');

    canvas.width = width;
    canvas.height = height;
    canvas
        .getContext('2d')
        ?.drawImage(bitmap, crop.x, crop.y, crop.width, crop.height, 0, 0, width, height);
    bitmap.close();

    return new Promise((resolve) => {
        canvas.toBlob((blob) => {
            resolve(blob ? new File([blob], file.name, { type: file.type }) : file);
        }, file.type);
    });
}
