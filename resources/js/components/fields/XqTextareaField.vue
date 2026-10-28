<script setup lang="ts">
import { useField } from '../../composables/useField';
import type { SchemaNode } from '../../schema/types';
import XqTextarea from '../../ui/XqTextarea.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';

const props = defineProps<{ node: SchemaNode }>();
const { path, value, error, required, disabled, id, onBlur } = useField<string | null>(props.node);
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required">
        <XqTextarea
            :id="id"
            v-model="value"
            :name="path"
            :rows="node.props?.rows ?? 3"
            :maxlength="node.props?.maxLength ?? undefined"
            :placeholder="node.placeholder ?? undefined"
            :disabled="disabled"
            :aria-invalid="error ? true : undefined"
            @blur="onBlur"
        />
    </XqFieldWrapper>
</template>
