<script setup lang="ts">
import { cn } from '../lib/utils';
import { actionBadgeClasses } from '../ui/classes';

defineProps<{ badge: string | number; color?: string | null }>();
</script>

<template>
    <span
        :class="
            cn(
                'px-1 font-semibold leading-4 -top-1.5 -right-1.5 min-w-4 pointer-events-none absolute z-10 rounded-full text-center text-[0.625rem] tabular-nums',
                actionBadgeClasses[color ?? 'primary'] ?? actionBadgeClasses.primary,
            )
        "
        data-xq-action-badge
    >
        {{ badge }}
    </span>
</template>
