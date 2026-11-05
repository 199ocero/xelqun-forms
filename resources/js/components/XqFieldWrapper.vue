<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { useFormStore } from '../composables/context';
import type { SchemaNode } from '../schema/types';
import XqLabel from '../ui/XqLabel.vue';

const props = defineProps<{
    node: SchemaNode;
    id: string;
    /** The control the label points at, or null when it only names a group. */
    labelFor?: string | null;
    /** Announce the error as soon as it appears, for errors outside a submit. */
    announceError?: boolean;
    error?: string | null;
    required?: boolean;
    hideLabel?: boolean;
    /** The control shows its own spinner, so the label doesn't need one. */
    controlShowsLoading?: boolean;
}>();

const store = useFormStore();
const loading = computed(() => store.isLoading(props.node));
const label = computed(() => store.text(props.node, 'label'));
const hint = computed(() => store.text(props.node, 'hint'));
const showLabelSpinner = computed(
    () => !props.controlShowsLoading && store.showsLoadingIndicator(props.node),
);
</script>

<template>
    <div
        class="gap-2 grid"
        :data-xq-path="node.path"
        :data-invalid="error ? '' : undefined"
        :aria-busy="loading || undefined"
    >
        <XqLabel
            v-if="label && !hideLabel"
            :id="`${id}-label`"
            :for="labelFor === null ? undefined : (labelFor ?? id)"
            class="gap-0.5"
        >
            {{ label }}
            <span v-if="required" class="text-xq-destructive" aria-hidden="true">*</span>
            <span v-if="required" class="sr-only">(required)</span>
            <LoaderCircle
                v-if="showLabelSpinner"
                class="ml-1 size-3 motion-safe:animate-spin text-xq-muted-foreground"
                aria-hidden="true"
            />
        </XqLabel>
        <slot />
        <p v-if="hint && !error" :id="`${id}-hint`" class="text-xs text-xq-muted-foreground">
            {{ hint }}
        </p>
        <p
            v-if="error"
            :id="`${id}-error`"
            :role="announceError ? 'alert' : undefined"
            class="text-xs font-medium text-xq-destructive"
        >
            {{ error }}
        </p>
    </div>
</template>
