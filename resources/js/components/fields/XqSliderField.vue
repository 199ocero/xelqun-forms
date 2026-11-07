<script setup lang="ts">
import { SliderRange, SliderRoot, SliderThumb, SliderTrack } from 'reka-ui';
import { computed } from 'vue';
import { useField } from '../../composables/useField';
import type { SchemaNode } from '../../schema/types';
import XqFieldWrapper from '../XqFieldWrapper.vue';

const props = defineProps<{ node: SchemaNode }>();
const { value, error, label, required, disabled, id, onBlur } = useField<number | number[] | null>(
    props.node,
);
const options = computed(() => props.node.props ?? {});

const handles = computed<number[]>({
    get: () => (Array.isArray(value.value) ? value.value : [value.value ?? options.value.min]),
    set: (next) => (value.value = Array.isArray(value.value) ? next : next[0]),
});
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :label-for="null" :error="error" :required="required">
        <div class="gap-3 flex items-center">
            <SliderRoot
                v-model="handles"
                :min="options.min"
                :max="options.max"
                :step="options.step"
                :disabled="disabled"
                class="h-5 relative flex flex-1 touch-none items-center select-none data-[disabled]:opacity-50"
                @value-commit="onBlur"
            >
                <SliderTrack class="h-1.5 relative grow rounded-full bg-xq-muted">
                    <SliderRange class="absolute h-full rounded-full bg-xq-primary" />
                </SliderTrack>
                <SliderThumb
                    v-for="(_, index) in handles"
                    :key="index"
                    :aria-label="label ?? undefined"
                    class="size-4 shadow-xs block cursor-pointer rounded-full border border-xq-primary bg-xq-card outline-none focus-visible:ring-[3px] focus-visible:ring-xq-ring/30"
                />
            </SliderRoot>
            <output v-if="options.tooltips" class="text-sm text-right tabular-nums">
                {{ handles.join(' – ') }}
            </output>
        </div>
    </XqFieldWrapper>
</template>
