<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue';
import { computed, inject } from 'vue';
import { FUSED_GROUP, useFormStore } from '../composables/context';
import { cn } from '../lib/utils';
import type { SchemaNode } from '../schema/types';
import XqLabel from '../ui/XqLabel.vue';
import XqFieldHint from './XqFieldHint.vue';
import XqFieldSlot from './XqFieldSlot.vue';

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
const helperText = computed(() => store.text(props.node, 'helperText'));
const hasHint = computed(() => !!(store.text(props.node, 'hint') || props.node.hintIcon));
const showsRequired = computed(() => props.node.markedAsRequired ?? props.required);
// A hidden label takes no grid space, so two columns would squeeze the control.
const inlineLabel = computed(() => props.node.inlineLabel && !props.node.hiddenLabel);
const showLabelSpinner = computed(
    () => !props.controlShowsLoading && store.showsLoadingIndicator(props.node),
);
const fused = inject(FUSED_GROUP, false);
const hasSlot = (name: string) => props.node.children?.some((child) => child.slot === name);
const hasLabelSlots = computed(
    () => hasSlot('beforeLabel') || hasSlot('afterLabel') || hasHint.value,
);
// A wrapper around a screen-reader label would be an empty row with a gap.
const hasStackedLabel = computed(
    () => hasLabelSlots.value || hasSlot('aboveLabel') || hasSlot('belowLabel'),
);
const hasContentSlots = computed(() =>
    ['beforeContent', 'afterContent', 'prefixAction', 'suffixAction', 'optionAction'].some(hasSlot),
);
</script>

<template>
    <div
        :class="
            cn(
                'gap-2 grid data-invalid:animate-xq-shake',
                inlineLabel && 'sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]',
            )
        "
        :data-xq-path="node.path"
        :data-invalid="error ? '' : undefined"
        :aria-busy="loading || undefined"
    >
        <div :class="hasStackedLabel ? 'gap-2 grid content-start' : 'contents'">
            <XqFieldSlot :node="node" name="aboveLabel" />
            <div :class="hasLabelSlots ? 'gap-2 flex items-center' : 'contents'">
                <XqFieldSlot :node="node" name="beforeLabel" />
                <XqLabel
                    v-if="label && !hideLabel"
                    :id="`${id}-label`"
                    :for="labelFor === null ? undefined : (labelFor ?? id)"
                    :class="cn('gap-0.5', node.hiddenLabel && 'sr-only', inlineLabel && 'sm:mt-2')"
                >
                    {{ label }}
                    <span v-if="showsRequired" class="text-xq-destructive" aria-hidden="true">
                        *
                    </span>
                    <span v-if="showsRequired" class="sr-only">{{ store.t('(required)') }}</span>
                    <LoaderCircle
                        v-if="showLabelSpinner"
                        class="ml-1 size-3 motion-safe:animate-spin text-xq-muted-foreground"
                        aria-hidden="true"
                    />
                </XqLabel>
                <XqFieldHint v-if="hasHint && !hideLabel" :node="node" />
                <XqFieldSlot :class="!hasHint && 'ms-auto'" :node="node" name="afterLabel" />
            </div>
            <XqFieldSlot :node="node" name="belowLabel" />
        </div>
        <div :class="inlineLabel ? 'gap-2 min-w-0 grid' : 'contents'">
            <XqFieldSlot :node="node" name="aboveContent" />
            <div :class="hasContentSlots ? 'gap-2 flex items-center' : 'contents'">
                <XqFieldSlot :node="node" name="beforeContent" />
                <XqFieldSlot :node="node" name="prefixAction" />
                <div :class="hasContentSlots ? 'min-w-0 flex-1' : 'contents'">
                    <slot />
                </div>
                <XqFieldSlot :node="node" name="optionAction" />
                <XqFieldSlot :node="node" name="suffixAction" />
                <XqFieldSlot :node="node" name="afterContent" />
            </div>
            <XqFieldSlot :node="node" name="belowContent" />
            <p
                v-if="helperText && !error"
                :id="`${id}-helper`"
                class="text-xs text-xq-muted-foreground"
            >
                {{ helperText }}
            </p>
            <XqFieldSlot :node="node" name="aboveErrorMessage" />
            <p
                v-if="error && !fused"
                :id="`${id}-error`"
                :role="announceError ? 'alert' : undefined"
                class="text-xs font-medium animate-xq-error-in text-xq-destructive"
            >
                {{ error }}
            </p>
            <XqFieldSlot :node="node" name="belowErrorMessage" />
        </div>
    </div>
</template>
