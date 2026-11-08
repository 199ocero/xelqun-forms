<script setup lang="ts">
import { Check, LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import { cn } from '../lib/utils';

/**
 * What's on a button that runs something: its label, then a spinner if it takes a
 * while, then a check that draws on when it's done. All three share one spot, so
 * the button never changes size.
 */
const props = defineProps<{ spinning?: boolean; done?: boolean }>();

const isHidden = computed(() => props.spinning || props.done);
</script>

<template>
    <span class="grid place-items-center">
        <span
            :class="
                cn(
                    'gap-1.5 inline-flex items-center transition-[opacity,scale] duration-(--xq-duration-fast) [grid-area:1/1]',
                    isHidden && 'scale-90 opacity-0',
                )
            "
        >
            <slot />
        </span>
        <span v-if="spinning" class="animate-xq-pop-in [grid-area:1/1]" aria-hidden="true">
            <LoaderCircle class="motion-safe:animate-spin" />
        </span>
        <span v-else-if="done" class="[grid-area:1/1]" aria-hidden="true" data-xq-action-done>
            <Check class="[&_path]:animate-xq-check-draw [&_path]:[stroke-dasharray:24]" />
        </span>
    </span>
</template>
