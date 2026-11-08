<script setup lang="ts">
import { HttpError, useField, XqFieldWrapper } from '@xelqun/forms';
import type { SchemaNode } from '@xelqun/forms';
import { computed, ref } from 'vue';

const props = defineProps<{ node: SchemaNode }>();
const { store, path, value, error, describedBy, required, disabled, id } =
    useField<string | null>(props.node);
const uploadError = ref<string | null>(null);
const fileName = ref<string | null>(null);
const shownName = computed(
    () => fileName.value ?? value.value?.split('/').pop() ?? null,
);

async function upload(event: Event): Promise<void> {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    uploadError.value = null;

    try {
        const result = await store.upload(path, file, () => {});

        fileName.value = result.name;
        value.value = result.token;
    } catch (failure) {
        uploadError.value =
            failure instanceof HttpError
                ? (failure.validationErrors.file?.[0] ?? failure.message)
                : 'The upload failed.';
    }
}
</script>

<template>
    <XqFieldWrapper
        :node="node"
        :id="id"
        :error="error ?? uploadError"
        :required="required"
    >
        <input
            :id="id"
            type="file"
            class="text-sm text-xq-foreground"
            :accept="node.props?.accept ?? undefined"
            :disabled="disabled"
            :aria-invalid="error || uploadError ? true : undefined"
            :aria-describedby="uploadError ? `${id}-error` : describedBy"
            @change="upload"
        />
        <p v-if="shownName" class="text-sm text-xq-foreground">
            {{ shownName }}
        </p>
    </XqFieldWrapper>
</template>
