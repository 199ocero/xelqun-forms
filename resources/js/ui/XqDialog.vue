<script setup lang="ts">
import {
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { cn } from '../lib/utils';

const props = defineProps<{ title: string; description?: string | null; width?: string | null }>();
const open = defineModel<boolean>('open', { default: false });

const widths: Record<string, string> = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
};
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="inset-0 bg-black/50 fixed z-50" />
            <DialogContent
                :class="
                    cn(
                        'p-5 shadow-lg gap-4 fixed top-1/2 left-1/2 z-50 grid max-h-[85vh] w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-xq-lg border border-xq-border bg-xq-popover text-xq-popover-foreground',
                        widths[props.width ?? 'md'],
                    )
                "
            >
                <div class="gap-1 grid">
                    <DialogTitle class="text-sm font-semibold">{{ title }}</DialogTitle>
                    <DialogDescription class="text-sm text-xq-muted-foreground">
                        {{ description ?? '' }}
                    </DialogDescription>
                </div>
                <slot />
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
