<script setup lang="ts">
import { useField } from '../../composables/useField';
import type { SchemaNode } from '../../schema/types';
import XqCheckbox from '../../ui/XqCheckbox.vue';
import XqLabel from '../../ui/XqLabel.vue';

const props = defineProps<{ node: SchemaNode }>();
const { value, error, required, disabled, id } = useField<boolean>(props.node);
</script>

<template>
    <div class="gap-2 grid" :data-xq-path="node.path">
        <div class="gap-3 flex items-start">
            <XqCheckbox
                :id="id"
                v-model="value"
                :disabled="disabled"
                :aria-invalid="error ? true : undefined"
                class="mt-0.5"
            />
            <div class="gap-1.5 grid">
                <XqLabel :for="id" class="gap-0.5 leading-snug cursor-pointer">
                    {{ node.label }}
                    <span v-if="required" class="text-xq-destructive" aria-hidden="true">*</span>
                </XqLabel>
                <p
                    v-if="node.props?.description || node.hint"
                    class="text-xs text-xq-muted-foreground"
                >
                    {{ node.props?.description ?? node.hint }}
                </p>
            </div>
        </div>
        <p
            v-if="error"
            :id="`${id}-error`"
            role="alert"
            class="text-xs font-medium text-xq-destructive"
        >
            {{ error }}
        </p>
    </div>
</template>
