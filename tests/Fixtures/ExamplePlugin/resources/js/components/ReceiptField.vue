<script setup lang="ts">
import { HttpError, useField, XqFieldWrapper } from '@xelqun/forms';
import type { SchemaNode } from '@xelqun/forms';
import { computed, ref } from 'vue';

const props = defineProps<{ node: SchemaNode }>();
const { store, path, value, error, describedBy, required, disabled, id } = useField<string | null>(
    props.node,
);
const options = computed(() => props.node.props ?? {});
const uploadError = ref<string | null>(null);
const name = ref<string | null>(null);

async function upload(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    uploadError.value = null;

    try {
        const result = await store.upload(path, file, () => {});

        name.value = result.name;
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
    <XqFieldWrapper :node="node" :id="id" :error="error ?? uploadError" :required="required">
        <p class="text-xs text-xq-muted-foreground">{{ options.caption }}</p>
        <input
            :id="id"
            type="file"
            class="text-sm text-xq-foreground"
            :accept="options.accept ?? undefined"
            :disabled="disabled"
            :aria-invalid="error || uploadError ? true : undefined"
            :aria-describedby="uploadError ? `${id}-error` : describedBy"
            @change="upload"
        />
        <p v-if="name" class="text-sm text-xq-foreground" data-receipt-name>{{ name }}</p>
    </XqFieldWrapper>
</template>
