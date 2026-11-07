<script setup lang="ts">
import { RadioGroupIndicator, RadioGroupItem } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const props = defineProps<{
    id?: string;
    value: string | number;
    disabled?: boolean;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <RadioGroupItem
        :id="id"
        data-slot="xq-radio-group-item"
        :value="value"
        :disabled="disabled"
        :class="
            cn(
                'size-4 shadow-xs aspect-square shrink-0 cursor-pointer rounded-full border border-xq-input bg-xq-card transition-shadow outline-none focus-visible:ring-[3px] focus-visible:ring-xq-ring/30 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:border-xq-primary',
                props.class,
            )
        "
    >
        <RadioGroupIndicator class="flex items-center justify-center">
            <span class="size-2 rounded-full bg-xq-primary" />
        </RadioGroupIndicator>
    </RadioGroupItem>
</template>
