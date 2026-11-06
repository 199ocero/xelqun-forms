<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';
import { resolveIcon } from './icons';

const props = defineProps<{ name: string; class?: HTMLAttributes['class'] }>();
const icon = computed(() => {
    const resolved = resolveIcon(props.name);

    if (!resolved && import.meta.env?.DEV) {
        console.warn(
            `[xelqun] No icon is registered as "${props.name}". Add it with the icons option.`,
        );
    }

    return resolved;
});
</script>

<template>
    <component
        :is="icon"
        v-if="icon"
        :class="cn('size-4 shrink-0', props.class)"
        aria-hidden="true"
    />
</template>
