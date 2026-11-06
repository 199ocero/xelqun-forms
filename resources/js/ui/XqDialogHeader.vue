<script setup lang="ts">
import { cn } from '../lib/utils';
import { dialogIconClasses } from './classes';
import XqIcon from './XqIcon.vue';

defineProps<{
    icon?: string | null;
    iconColor?: string | null;
    alignment?: 'start' | 'center' | null;
}>();
</script>

<template>
    <div
        :class="
            cn(
                'gap-4 flex',
                alignment === 'center' ? 'flex-col items-center text-center' : 'items-start',
            )
        "
    >
        <span
            v-if="icon"
            data-xq-dialog-icon
            :class="
                cn(
                    'size-10 flex shrink-0 items-center justify-center rounded-full',
                    dialogIconClasses[iconColor ?? 'primary'] ?? dialogIconClasses.primary,
                )
            "
        >
            <XqIcon :name="icon" class="size-5" />
        </span>
        <div class="gap-1 min-w-0 grid">
            <slot />
        </div>
    </div>
</template>
