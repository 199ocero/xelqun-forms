<script setup lang="ts">
import { Label } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const props = defineProps<{ for?: string; class?: HTMLAttributes['class'] }>();
</script>

<template>
    <Label
        data-slot="xq-label"
        :for="props.for"
        :class="cn('text-sm font-medium leading-none text-xq-foreground select-none', props.class)"
    >
        <slot />
    </Label>
</template>
