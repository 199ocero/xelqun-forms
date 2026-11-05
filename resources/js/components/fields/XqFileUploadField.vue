<script setup lang="ts">
import { FileText, LoaderCircle, UploadCloud, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { useField } from '../../composables/useField';
import { HttpError } from '../../core/http';
import { cn, formatBytes } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import XqButton from '../../ui/XqButton.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';

const props = defineProps<{ node: SchemaNode }>();
const { store, path, value, error, describedBy, required, disabled, id } = useField<string | null>(
    props.node,
);
const options = computed(() => props.node.props ?? {});

const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const progress = ref<{ name: string; size: number; percent: number } | null>(null);
const uploadError = ref<string | null>(null);
const local = ref<{ token: string; name: string; size: number; preview: string | null } | null>(
    null,
);
let controller: AbortController | null = null;

const file = computed(() => {
    if (!value.value) {
        return null;
    }

    if (local.value?.token === value.value) {
        return {
            name: local.value.name,
            size: local.value.size,
            url: local.value.preview,
            isNew: true,
        };
    }

    const existing = options.value.files?.[value.value];

    return {
        name: existing?.name ?? value.value.split('/').pop() ?? 'File',
        size: existing?.size ?? null,
        url: existing?.url ?? null,
        isNew: false,
    };
});

const isImage = computed(
    () =>
        !!file.value?.url &&
        (options.value.image || /\.(png|jpe?g|gif|webp|avif|svg)$/i.test(file.value.name)),
);

const inputDescribedBy = computed(() => (uploadError.value ? `${id}-error` : describedBy.value));

function revokePreview(): void {
    if (local.value?.preview) {
        URL.revokeObjectURL(local.value.preview);
    }
}

async function upload(picked: File): Promise<void> {
    uploadError.value = null;

    if (options.value.maxSize && picked.size > options.value.maxSize * 1024) {
        uploadError.value = `The file may not be larger than ${formatBytes(options.value.maxSize * 1024)}.`;

        return;
    }

    controller?.abort();
    controller = new AbortController();
    progress.value = { name: picked.name, size: picked.size, percent: 0 };

    try {
        const result = await store.upload(
            path,
            picked,
            (percent) => progress.value && (progress.value.percent = percent),
            controller.signal,
        );

        revokePreview();
        local.value = {
            token: result.token,
            name: result.name,
            size: result.size,
            preview: picked.type.startsWith('image/') ? URL.createObjectURL(picked) : null,
        };
        value.value = result.token;
    } catch (failure) {
        if (controller?.signal.aborted) {
            return;
        }

        uploadError.value =
            failure instanceof HttpError
                ? (failure.validationErrors.file?.[0] ?? failure.message)
                : 'The upload failed. Please try again.';
    } finally {
        progress.value = null;

        if (input.value) {
            input.value.value = '';
        }
    }
}

function onChange(event: Event): void {
    const picked = (event.target as HTMLInputElement).files?.[0];

    if (picked) {
        void upload(picked);
    }
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    const picked = event.dataTransfer?.files?.[0];

    if (picked && !disabled.value) {
        void upload(picked);
    }
}

function remove(): void {
    controller?.abort();
    revokePreview();
    local.value = null;
    value.value = null;
}

onBeforeUnmount(() => {
    controller?.abort();
    revokePreview();
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
        <div
            v-if="file && !progress"
            class="gap-3 p-2 pr-3 flex items-center rounded-xq-md border border-xq-border bg-xq-background"
        >
            <img
                v-if="isImage"
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
                <a
                    v-if="file.url && !file.isNew"
                    :href="file.url"
                    target="_blank"
                    rel="noopener"
                    class="text-sm font-medium block truncate hover:underline"
                >
                    {{ file.name }}
                </a>
                <p v-else class="text-sm font-medium truncate">{{ file.name }}</p>
                <p class="text-xs text-xq-muted-foreground">
                    {{ file.isNew ? 'Uploaded, saved when you submit' : 'Stored'
                    }}<template v-if="file.size"> · {{ formatBytes(file.size) }}</template>
                </p>
            </div>
            <XqButton
                v-if="!disabled"
                type="button"
                variant="ghost"
                size="icon-sm"
                aria-label="Remove file"
                @click="remove"
            >
                <X />
            </XqButton>
        </div>
        <label
            v-else
            :for="id"
            :class="
                cn(
                    'gap-1.5 px-4 py-5 flex cursor-pointer flex-col items-center justify-center rounded-xq-md border border-dashed border-xq-input bg-xq-background text-center transition-[color,background-color,border-color,box-shadow] outline-none hover:bg-xq-muted/50 has-[input:focus-visible]:border-xq-ring has-[input:focus-visible]:ring-[3px] has-[input:focus-visible]:ring-xq-ring/25',
                    dragging && 'border-xq-primary bg-xq-primary/5',
                    (error || uploadError) && 'border-xq-destructive',
                    disabled && 'pointer-events-none opacity-60',
                )
            "
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <template v-if="progress">
                <LoaderCircle class="size-5 motion-safe:animate-spin text-xq-primary" />
                <span class="text-sm font-medium max-w-full truncate">{{ progress.name }}</span>
                <span
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-valuenow="progress.percent"
                    :aria-label="`Uploading ${progress.name}`"
                    class="h-1.5 w-40 overflow-hidden rounded-full bg-xq-muted"
                >
                    <span
                        class="block h-full bg-xq-primary transition-[width]"
                        :style="{ width: `${progress.percent}%` }"
                    />
                </span>
            </template>
            <template v-else>
                <UploadCloud class="size-5 text-xq-muted-foreground" />
                <span class="text-sm"
                    ><span class="font-medium text-xq-primary">Choose a file</span> or drag it
                    here</span
                >
                <span class="text-xs text-xq-muted-foreground"
                    >Up to {{ formatBytes((options.maxSize ?? 10240) * 1024) }}</span
                >
            </template>
            <input
                :id="id"
                ref="input"
                type="file"
                class="sr-only"
                :accept="options.accept ?? undefined"
                :disabled="disabled"
                :aria-invalid="error || uploadError ? true : undefined"
                :aria-describedby="inputDescribedBy"
                @change="onChange"
            />
        </label>
    </XqFieldWrapper>
</template>
