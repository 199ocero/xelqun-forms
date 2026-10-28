<script setup lang="ts">
import type { SchemaNode } from '../schema/types';
import XqLabel from '../ui/XqLabel.vue';

defineProps<{
    node: SchemaNode;
    id: string;
    error?: string | null;
    required?: boolean;
    hideLabel?: boolean;
}>();
</script>

<template>
    <div class="gap-2 grid" :data-xq-path="node.path" :data-invalid="error ? '' : undefined">
        <XqLabel v-if="node.label && !hideLabel" :for="id" class="gap-0.5">
            {{ node.label }}
            <span v-if="required" class="text-xq-destructive" aria-hidden="true">*</span>
        </XqLabel>
        <slot />
        <p v-if="node.hint && !error" :id="`${id}-hint`" class="text-xs text-xq-muted-foreground">
            {{ node.hint }}
        </p>
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
