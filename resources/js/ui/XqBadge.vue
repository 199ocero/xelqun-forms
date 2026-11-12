<script setup lang="ts">
import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const badgeVariants = cva(
    'gap-1 px-2 py-0.5 text-xs font-medium [&>svg]:size-3 inline-flex w-fit shrink-0 items-center rounded-(--xq-pill-radius) border whitespace-nowrap',
    {
        variants: {
            variant: {
                default: 'border-transparent bg-xq-primary text-xq-primary-foreground',
                secondary: 'border-transparent bg-xq-secondary text-xq-secondary-foreground',
                outline: 'border-xq-border text-xq-foreground',
                success: 'border-transparent bg-xq-success/15 text-xq-success',
                destructive: 'border-transparent bg-xq-destructive/15 text-xq-destructive',
            },
        },
        defaultVariants: { variant: 'default' },
    },
);

const props = defineProps<{
    variant?: VariantProps<typeof badgeVariants>['variant'];
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <span data-slot="xq-badge" :class="cn(badgeVariants({ variant }), props.class)">
        <slot />
    </span>
</template>
