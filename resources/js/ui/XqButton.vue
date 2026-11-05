<script setup lang="ts">
import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import { Primitive } from 'reka-ui';
import type { PrimitiveProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const buttonVariants = cva(
    'gap-2 text-sm font-medium [&_svg:not([class*=size-])]:size-4 inline-flex shrink-0 cursor-pointer items-center justify-center rounded-xq-md whitespace-nowrap transition-[color,background-color,box-shadow,opacity] outline-none focus-visible:ring-[3px] focus-visible:ring-xq-ring/40 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                default:
                    'shadow-xs bg-xq-primary text-xq-primary-foreground hover:bg-xq-primary/90',
                secondary: 'bg-xq-secondary text-xq-secondary-foreground hover:bg-xq-secondary/80',
                outline:
                    'shadow-xs border border-xq-border bg-xq-background text-xq-foreground hover:bg-xq-accent hover:text-xq-accent-foreground',
                ghost: 'text-xq-foreground hover:bg-xq-accent hover:text-xq-accent-foreground',
                destructive:
                    'shadow-xs bg-xq-destructive text-xq-destructive-foreground hover:bg-xq-destructive/90',
                link: 'text-xq-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-9 px-4 py-2',
                sm: 'h-8 gap-1.5 px-3 text-xs',
                lg: 'h-10 px-6',
                icon: 'size-9',
                'icon-sm': 'size-7',
            },
        },
        defaultVariants: { variant: 'default', size: 'default' },
    },
);

type ButtonVariants = VariantProps<typeof buttonVariants>;

const props = withDefaults(
    defineProps<
        PrimitiveProps & {
            variant?: ButtonVariants['variant'];
            size?: ButtonVariants['size'];
            class?: HTMLAttributes['class'];
        }
    >(),
    { as: 'button' },
);
</script>

<template>
    <Primitive
        data-slot="xq-button"
        :as="as"
        :as-child="asChild"
        :class="cn(buttonVariants({ variant, size }), props.class)"
    >
        <slot />
    </Primitive>
</template>
