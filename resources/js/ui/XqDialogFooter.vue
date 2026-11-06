<script setup lang="ts">
import { cn } from '../lib/utils';

defineProps<{
    alignment?: 'start' | 'center' | 'end' | 'full' | null;
    /** Keeps the buttons in reach while a long form scrolls behind them. */
    sticky?: boolean;
}>();

const alignments: Record<string, string> = {
    start: 'justify-start',
    center: 'justify-center',
    end: 'justify-end',
    full: '*:flex-1',
};
</script>

<template>
    <div
        data-xq-dialog-footer
        :class="
            cn(
                'gap-2 flex',
                alignments[alignment ?? 'end'] ?? alignments.end,
                sticky && 'bottom-0 -mx-6 px-6 py-4 sticky border-t border-xq-border bg-xq-popover',
            )
        "
    >
        <slot />
    </div>
</template>
