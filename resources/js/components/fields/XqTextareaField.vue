<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next';
import { useField } from '../../composables/useField';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { spinnerClass } from '../../ui/classes';
import XqTextarea from '../../ui/XqTextarea.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';

const props = defineProps<{ node: SchemaNode }>();
const {
    path,
    value,
    error,
    placeholder,
    describedBy,
    showLoading,
    required,
    disabled,
    id,
    onBlur,
} = useField<string | null>(props.node);
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required" control-shows-loading>
        <div class="relative">
            <XqTextarea
                :id="id"
                v-model="value"
                :name="path"
                :rows="node.props?.rows ?? 3"
                :maxlength="node.props?.maxLength ?? undefined"
                :placeholder="placeholder ?? undefined"
                :disabled="disabled"
                :aria-invalid="error ? true : undefined"
                :aria-describedby="describedBy"
                @blur="onBlur"
            />
            <LoaderCircle
                v-if="showLoading"
                :class="cn(spinnerClass, 'top-2.5 right-2.5 absolute')"
                aria-hidden="true"
            />
        </div>
    </XqFieldWrapper>
</template>
