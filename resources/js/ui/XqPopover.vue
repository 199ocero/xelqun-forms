<script setup lang="ts">
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';
import { popoverClass } from './classes';

const props = withDefaults(
    defineProps<{
        align?: 'start' | 'center' | 'end';
        sideOffset?: number;
        class?: HTMLAttributes['class'];
    }>(),
    { align: 'start', sideOffset: 4 },
);
const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger as-child>
            <slot name="trigger" />
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                data-slot="xq-popover"
                :align="align"
                :side-offset="sideOffset"
                :class="cn(popoverClass, 'p-3', props.class)"
            >
                <slot />
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
