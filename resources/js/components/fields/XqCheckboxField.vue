<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { useField } from '../../composables/useField';
import type { SchemaNode } from '../../schema/types';
import XqCheckbox from '../../ui/XqCheckbox.vue';
import XqLabel from '../../ui/XqLabel.vue';

const props = defineProps<{ node: SchemaNode }>();
const { value, error, label, hint, loading, showLoading, required, disabled, id } =
    useField<boolean>(props.node);
const hasHint = computed(() => !!(props.node.props?.description || hint.value));
const describedBy = computed(() =>
    error.value ? `${id}-error` : hasHint.value ? `${id}-hint` : undefined,
);
</script>

<template>
    <div class="gap-2 grid" :data-xq-path="node.path" :aria-busy="loading || undefined">
        <div class="gap-3 flex items-start">
            <XqCheckbox
                :id="id"
                v-model="value"
                :disabled="disabled"
                :aria-invalid="error ? true : undefined"
                :aria-describedby="describedBy"
                class="mt-0.5"
            />
            <div class="gap-1.5 grid">
                <XqLabel :for="id" class="gap-0.5 leading-snug cursor-pointer">
                    {{ label }}
                    <span v-if="required" class="text-xq-destructive" aria-hidden="true">*</span>
                    <span v-if="required" class="sr-only">(required)</span>
                    <LoaderCircle
                        v-if="showLoading"
                        class="ml-1 size-3 motion-safe:animate-spin text-xq-muted-foreground"
                        aria-hidden="true"
                    />
                </XqLabel>
                <p v-if="hasHint" :id="`${id}-hint`" class="text-xs text-xq-muted-foreground">
                    {{ node.props?.description ?? hint }}
                </p>
            </div>
        </div>
        <p v-if="error" :id="`${id}-error`" class="text-xs font-medium text-xq-destructive">
            {{ error }}
        </p>
    </div>
</template>
