<script setup lang="ts">
import {
    ChevronDown,
    ChevronUp,
    Download,
    ExternalLink,
    FileText,
    GripVertical,
    LoaderCircle,
    Pencil,
    UploadCloud,
    X,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, ref } from 'vue';
import { useField } from '../../composables/useField';
import { prepareImage } from '../../core/images';
import { useSortable } from '../../composables/useSortable';
import { HttpError } from '../../core/http';
import { cn, focusFirstEnabled, formatBytes } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { moveItem } from '../../ui/options';
import XqButton from '../../ui/XqButton.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';
import { useTranslator } from '../../composables/context';

const t = useTranslator();
const XqImageEditor = defineAsyncComponent(() => import('./XqImageEditor.vue'));

interface PendingFile {
    id: number;
    file: File;
    name: string;
    size: number;
    percent: number;
    status: 'waiting' | 'uploading' | 'finished';
    token: string | null;
    controller: AbortController;
}

const props = defineProps<{ node: SchemaNode }>();
const { store, path, value, error, describedBy, required, disabled, id } = useField<
    string | string[] | null
>(props.node);
const options = computed(() => props.node.props ?? {});
const isMultiple = computed(() => !!options.value.multiple);
// One at a time by default: on SQLite two uploads at once hit "database is locked" in the rate limiter.
const parallelLimit = computed<number>(() => options.value.maxParallelUploads ?? 1);

const input = ref<HTMLInputElement | null>(null);
const root = ref<HTMLElement | null>(null);
const fileList = ref<HTMLElement | null>(null);
const dragging = ref(false);
const uploadError = ref<string | null>(null);
const pending = ref<PendingFile[]>([]);
const editing = ref<ReturnType<typeof describe> | null>(null);
/** What this page knows about files it uploaded, before they have a stored path. */
const uploaded = ref(new Map<string, { name: string; size: number; preview: string | null }>());
let nextPendingId = 0;
let running = 0;

const values = computed<string[]>(() => {
    if (Array.isArray(value.value)) {
        return value.value;
    }

    return value.value ? [value.value] : [];
});

function setValues(next: string[]): void {
    value.value = isMultiple.value ? next : (next[0] ?? null);
}

function describe(file: string) {
    const local = uploaded.value.get(file);
    const known = options.value.files?.[file];
    const name = local?.name ?? known?.name ?? file.split('/').pop() ?? t('File');
    const url: string | null = local?.preview ?? known?.url ?? null;

    return {
        value: file,
        name,
        size: local?.size ?? known?.size ?? null,
        url,
        isNew: !!local,
        isImage:
            !!url &&
            options.value.previewable !== false &&
            (options.value.image || /\.(png|jpe?g|gif|webp|avif|svg)$/i.test(name)),
    };
}

const files = computed(() => values.value.map(describe));
const hasRoom = computed(() =>
    isMultiple.value
        ? values.value.length + pending.value.length < (options.value.maxFiles ?? Infinity)
        : values.value.length === 0 && pending.value.length === 0,
);
const canReorder = computed(
    () => !!options.value.reorderable && isMultiple.value && !disabled.value,
);
const inputDescribedBy = computed(() => (uploadError.value ? `${id}-error` : describedBy.value));
const isAvatar = computed(() => !!options.value.avatar);

useSortable(fileList, {
    draggable: '> [data-xq-file]',
    handle: `[data-xq-drag="${path}"]`,
    enabled: () => canReorder.value && values.value.length > 1,
    onMove: (from, to) => move(values.value[from], to - from),
});

function move(file: string, offset: number): void {
    setValues(moveItem(values.value, file, offset));
}

function fileSelector(file: string): string {
    return `[data-xq-file="${CSS.escape(file)}"]`;
}

/** The button moved with its row, and a move ending at the edge disables it. */
async function moveWithButton(file: string, offset: number): Promise<void> {
    move(file, offset);

    await nextTick();

    focusFirstEnabled(root.value, [
        `${fileSelector(file)} [data-xq-move="${offset}"]`,
        `${fileSelector(file)} [data-xq-move="${-offset}"]`,
    ]);
}

function forget(file: string): void {
    const local = uploaded.value.get(file);

    if (local?.preview) {
        URL.revokeObjectURL(local.preview);
    }

    uploaded.value.delete(file);
}

function canRemove(file: { isNew: boolean }): boolean {
    return !disabled.value && (file.isNew || options.value.deletable !== false);
}

function sizeProblem(picked: File): string | null {
    if (options.value.maxSize && picked.size > options.value.maxSize * 1024) {
        return t('The file may not be larger than :size.', {
            size: formatBytes(options.value.maxSize * 1024),
        });
    }

    if (options.value.minSize && picked.size < options.value.minSize * 1024) {
        return t('The file must be at least :size.', {
            size: formatBytes(options.value.minSize * 1024),
        });
    }

    return null;
}

/**
 * Add finished files in the order they were picked, whichever finished first.
 */
function collectFinished(): void {
    while (pending.value[0]?.status === 'finished') {
        const { token } = pending.value.shift()!;

        if (token) {
            setValues(
                isMultiple.value && options.value.appendFiles !== false
                    ? [...values.value, token]
                    : [token, ...values.value],
            );
        }
    }
}

async function uploadOne(item: PendingFile): Promise<void> {
    item.status = 'uploading';

    try {
        const file = await prepareImage(item.file, options.value.imageResize);
        const result = await store.upload(
            path,
            file,
            (percent) => (item.percent = percent),
            item.controller.signal,
        );

        uploaded.value.set(result.token, {
            name: result.name,
            size: result.size,
            preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
        });
        item.token = result.token;
    } catch (failure) {
        if (!item.controller.signal.aborted) {
            uploadError.value =
                failure instanceof HttpError
                    ? (failure.validationErrors.file?.[0] ?? failure.message)
                    : t('The upload failed. Please try again.');
        }
    } finally {
        item.status = 'finished';
        collectFinished();
    }
}

async function work(): Promise<void> {
    running++;

    for (
        let item = pending.value.find(({ status }) => status === 'waiting');
        item;
        item = pending.value.find(({ status }) => status === 'waiting')
    ) {
        await uploadOne(item);
    }

    running--;
}

function addFiles(picked: File[]): void {
    uploadError.value = null;

    const room = isMultiple.value
        ? (options.value.maxFiles ?? Infinity) - values.value.length - pending.value.length
        : 1;

    if (picked.length > room) {
        uploadError.value =
            room === 1
                ? t('You can add 1 more file.')
                : t('You can add :count more files.', { count: room });
    }

    for (const file of picked.slice(0, room)) {
        const problem = sizeProblem(file);

        if (problem) {
            uploadError.value = problem;

            continue;
        }

        pending.value.push({
            id: nextPendingId++,
            file,
            name: file.name,
            size: file.size,
            percent: 0,
            status: 'waiting',
            token: null,
            controller: new AbortController(),
        });
    }

    if (input.value) {
        input.value.value = '';
    }

    const workers = Math.min(
        parallelLimit.value - running,
        pending.value.filter(({ status }) => status === 'waiting').length,
    );

    for (let count = 0; count < workers; count++) {
        store.trackUpload(work());
    }
}

function onChange(event: Event): void {
    addFiles([...((event.target as HTMLInputElement).files ?? [])]);
}

function onDrop(event: DragEvent): void {
    dragging.value = false;

    if (!disabled.value) {
        addFiles([...(event.dataTransfer?.files ?? [])]);
    }
}

function onPaste(event: ClipboardEvent): void {
    const pasted = [...(event.clipboardData?.files ?? [])];

    if (options.value.pasteable !== false && pasted.length && !disabled.value && hasRoom.value) {
        event.preventDefault();
        addFiles(pasted);
    }
}

function cancel(item: PendingFile): void {
    item.controller.abort();
    pending.value = pending.value.filter((other) => other.id !== item.id);
    collectFinished();
}

function cancelAll(): void {
    pending.value.forEach((item) => item.controller.abort());
    pending.value = [];
}

/**
 * The edited image uploads as a new file, in the place of the old one. The server
 * gives a stored file back if it may not go, so that one can't be edited.
 */
function canEdit(file: ReturnType<typeof describe>): boolean {
    return (
        !!options.value.imageEditor &&
        file.isImage &&
        !disabled.value &&
        (file.isNew || options.value.deletable !== false)
    );
}

async function saveEdit(edited: File): Promise<void> {
    const original = editing.value!.value;

    editing.value = null;
    uploadError.value = null;

    try {
        const result = await store.upload(path, edited, () => {});

        uploaded.value.set(result.token, {
            name: result.name,
            size: result.size,
            preview: URL.createObjectURL(edited),
        });
        setValues(values.value.map((file) => (file === original ? result.token : file)));
        forget(original);
    } catch (failure) {
        uploadError.value =
            failure instanceof HttpError
                ? (failure.validationErrors.file?.[0] ?? failure.message)
                : t('The upload failed. Please try again.');
    }
}

async function remove(file: string): Promise<void> {
    const index = values.value.indexOf(file);

    forget(file);
    setValues(values.value.filter((other) => other !== file));

    await nextTick();

    const next = values.value[Math.min(index, values.value.length - 1)];

    focusFirstEnabled(root.value, [
        ...(next ? [`${fileSelector(next)} [data-xq-remove]`] : []),
        'input[type="file"]',
    ]);
}

onBeforeUnmount(() => {
    cancelAll();
    [...uploaded.value.keys()].forEach(forget);
});
</script>

<template>
    <XqFieldWrapper
        :node="node"
        :id="id"
        :error="error ?? uploadError"
        :required="required"
        :announce-error="!!uploadError"
    >
        <div ref="root" class="gap-2 grid" @paste="onPaste">
            <div
                v-if="isAvatar && files[0]"
                class="size-24 relative"
                :data-xq-file="files[0].value"
            >
                <img
                    v-if="files[0].isImage"
                    :src="files[0].url!"
                    :alt="files[0].name"
                    class="size-24 rounded-full object-cover"
                />
                <div
                    v-else
                    class="size-24 flex items-center justify-center rounded-full bg-xq-muted"
                >
                    <FileText class="size-6 text-xq-muted-foreground" />
                </div>
                <XqButton
                    v-if="canEdit(files[0])"
                    type="button"
                    variant="outline"
                    size="icon-sm"
                    class="right-0 bottom-0 absolute rounded-full"
                    :aria-label="t('Edit :name', { name: files[0].name })"
                    data-xq-edit-image
                    @click="editing = files[0]"
                >
                    <Pencil />
                </XqButton>
                <XqButton
                    v-if="canRemove(files[0])"
                    type="button"
                    variant="outline"
                    size="icon-sm"
                    class="top-0 right-0 absolute rounded-full"
                    :aria-label="t('Remove :name', { name: files[0].name })"
                    data-xq-remove
                    @click="remove(files[0].value)"
                >
                    <X />
                </XqButton>
            </div>
            <div v-else-if="files.length" ref="fileList" class="gap-2 grid">
                <div
                    v-for="(file, index) in files"
                    :key="file.value"
                    :data-xq-file="file.value"
                    class="gap-3 p-2 pr-3 flex items-center rounded-xq-md border border-xq-border bg-xq-card"
                >
                    <span
                        v-if="canReorder && files.length > 1"
                        aria-hidden="true"
                        :data-xq-drag="path"
                        class="cursor-grab touch-none text-xq-muted-foreground"
                    >
                        <GripVertical class="size-4" />
                    </span>
                    <img
                        v-if="file.isImage"
                        :src="file.url!"
                        alt=""
                        class="size-10 shrink-0 rounded-xq-sm object-cover"
                    />
                    <div
                        v-else
                        class="size-10 flex shrink-0 items-center justify-center rounded-xq-sm bg-xq-muted"
                    >
                        <FileText class="size-5 text-xq-muted-foreground" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium truncate">{{ file.name }}</p>
                        <p class="text-xs text-xq-muted-foreground">
                            {{ file.isNew ? t('Uploaded, saved when you submit') : t('Stored')
                            }}<template v-if="file.size"> · {{ formatBytes(file.size) }}</template>
                        </p>
                    </div>
                    <template v-if="canReorder && files.length > 1">
                        <XqButton
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            :disabled="index === 0"
                            :aria-label="t('Move :name up', { name: file.name })"
                            data-xq-move="-1"
                            @click="moveWithButton(file.value, -1)"
                        >
                            <ChevronUp />
                        </XqButton>
                        <XqButton
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            :disabled="index === files.length - 1"
                            :aria-label="t('Move :name down', { name: file.name })"
                            data-xq-move="1"
                            @click="moveWithButton(file.value, 1)"
                        >
                            <ChevronDown />
                        </XqButton>
                    </template>
                    <XqButton
                        v-if="canEdit(file)"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="t('Edit :name', { name: file.name })"
                        data-xq-edit-image
                        @click="editing = file"
                    >
                        <Pencil />
                    </XqButton>
                    <XqButton
                        v-if="options.openable && file.url && !file.isNew"
                        as="a"
                        :href="file.url"
                        target="_blank"
                        rel="noopener"
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="t('Open :name', { name: file.name })"
                    >
                        <ExternalLink />
                    </XqButton>
                    <XqButton
                        v-if="options.downloadable && file.url && !file.isNew"
                        as="a"
                        :href="file.url"
                        :download="file.name"
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="t('Download :name', { name: file.name })"
                    >
                        <Download />
                    </XqButton>
                    <XqButton
                        v-if="canRemove(file)"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="
                            isMultiple ? t('Remove :name', { name: file.name }) : t('Remove file')
                        "
                        data-xq-remove
                        @click="remove(file.value)"
                    >
                        <X />
                    </XqButton>
                </div>
            </div>
            <div
                v-for="item in pending"
                :key="item.id"
                class="gap-3 p-2 pr-3 flex items-center rounded-xq-md border border-dashed border-xq-border bg-xq-card"
            >
                <LoaderCircle class="size-5 motion-safe:animate-spin shrink-0 text-xq-primary" />
                <div class="min-w-0 gap-1 grid flex-1">
                    <span class="text-sm font-medium truncate">{{ item.name }}</span>
                    <span
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-valuenow="item.percent"
                        :aria-label="t('Uploading :name', { name: item.name })"
                        class="h-1.5 w-full overflow-hidden rounded-full bg-xq-muted"
                    >
                        <span
                            class="block h-full bg-xq-primary transition-[width]"
                            :style="{ width: `${item.percent}%` }"
                        />
                    </span>
                </div>
                <XqButton
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="t('Cancel :name', { name: item.name })"
                    @click="cancel(item)"
                >
                    <X />
                </XqButton>
            </div>
            <label
                v-if="hasRoom"
                :for="id"
                :class="
                    cn(
                        'gap-1.5 px-4 py-5 flex cursor-pointer flex-col items-center justify-center border border-dashed border-xq-input bg-xq-card text-center transition-[color,background-color,border-color,box-shadow] outline-none hover:bg-xq-muted/50 has-[input:focus-visible]:border-xq-ring has-[input:focus-visible]:ring-[3px] has-[input:focus-visible]:ring-xq-ring/25',
                        isAvatar ? 'size-24 rounded-full' : 'rounded-xq-md',
                        dragging && 'border-xq-primary bg-xq-primary/5',
                        (error || uploadError) && 'border-xq-destructive',
                        disabled && 'pointer-events-none opacity-60',
                    )
                "
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <UploadCloud class="size-5 text-xq-muted-foreground" />
                <template v-if="!isAvatar">
                    <span class="text-sm"
                        ><span class="font-medium text-xq-primary">{{
                            isMultiple ? t('Choose files') : t('Choose a file')
                        }}</span>
                        {{ isMultiple ? t('or drag them here') : t('or drag it here') }}</span
                    >
                    <span class="text-xs text-xq-muted-foreground">{{
                        t('Up to :size', { size: formatBytes((options.maxSize ?? 10240) * 1024) })
                    }}</span>
                </template>
                <span v-else class="sr-only">{{ t('Choose an image') }}</span>
                <input
                    :id="id"
                    ref="input"
                    type="file"
                    class="sr-only"
                    :multiple="isMultiple"
                    :accept="options.accept ?? undefined"
                    :disabled="disabled"
                    :aria-invalid="error || uploadError ? true : undefined"
                    :aria-describedby="inputDescribedBy"
                    @change="onChange"
                />
            </label>
        </div>
        <XqImageEditor
            v-if="editing"
            :src="editing.url!"
            :name="editing.name"
            :aspect-ratios="options.imageEditor?.aspectRatios"
            :fill-color="options.imageEditor?.fillColor"
            @save="saveEdit"
            @cancel="editing = null"
        />
    </XqFieldWrapper>
</template>
