<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <div
        data-slot="xq-card"
        :class="
            cn(
                'shadow-xs rounded-xq-lg border border-xq-border bg-xq-card text-xq-card-foreground',
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
