<script setup lang="ts">
import { useField, XqFieldWrapper, XqInput } from '@xelqun/forms';
import type { SchemaNode } from '@xelqun/forms';

const props = defineProps<{ node: SchemaNode }>();
const {
    value,
    error,
    placeholder,
    describedBy,
    required,
    disabled,
    id,
    onBlur,
} = useField<string | null>(props.node);
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required">
        <XqInput
            :id="id"
            :model-value="value ?? ''"
            :placeholder="placeholder ?? undefined"
            :disabled="disabled"
            :aria-invalid="error ? true : undefined"
            :aria-describedby="describedBy"
            @update:model-value="value = String($event)"
            @blur="onBlur"
        />
    </XqFieldWrapper>
</template>
