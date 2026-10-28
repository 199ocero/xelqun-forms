<script setup lang="ts">
import { RadioGroupRoot } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const props = defineProps<{
    disabled?: boolean;
    orientation?: 'horizontal' | 'vertical';
    class?: HTMLAttributes['class'];
}>();
const model = defineModel<string | number | null>();
</script>

<template>
    <RadioGroupRoot
        v-model="model"
        data-slot="xq-radio-group"
        :disabled="disabled"
        :orientation="orientation"
        :class="cn('gap-2.5 grid', props.class)"
    >
        <slot />
    </RadioGroupRoot>
</template>
