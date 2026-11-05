<script setup lang="ts">
import { useField, XqFieldWrapper } from '@xelqun/forms';
import type { SchemaNode } from '@xelqun/forms';
import { computed } from 'vue';

const props = defineProps<{ node: SchemaNode }>();
const { value, error, required, disabled, id } = useField<number | null>(props.node);
const stars = computed<number[]>(() =>
    Array.from({ length: props.node.props?.max ?? 5 }, (_, index) => index + 1),
);
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required" :label-for="null">
        <div role="group" :aria-labelledby="`${id}-label`" class="flex gap-1">
            <button
                v-for="star in stars"
                :key="star"
                type="button"
                class="size-8 rounded-xq-md border border-xq-input text-sm text-xq-foreground aria-pressed:bg-xq-primary aria-pressed:text-xq-primary-foreground"
                :aria-label="`${star} of ${stars.length}`"
                :aria-pressed="value === star"
                :disabled="disabled"
                :data-star="star"
                @click="value = star"
            >
                {{ star }}
            </button>
        </div>
    </XqFieldWrapper>
</template>
