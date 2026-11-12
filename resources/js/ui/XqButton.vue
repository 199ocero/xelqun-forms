<script setup lang="ts">
import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import { Primitive } from 'reka-ui';
import type { PrimitiveProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const buttonVariants = cva(
    'gap-2 text-sm font-medium [&_svg:not([class*=size-])]:size-4 relative inline-flex shrink-0 cursor-pointer items-center justify-center rounded-(--xq-button-radius) whitespace-nowrap transition-[color,background-color,box-shadow,opacity,scale] duration-(--xq-duration-fast) outline-none focus-visible:ring-[3px] focus-visible:ring-xq-ring/40 not-disabled:active:scale-[0.97] disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg]:transition-transform [&_svg]:duration-(--xq-duration-base) [&_svg]:ease-(--xq-ease-spring) not-disabled:hover:[&_svg]:scale-110',
    {
        variants: {
            variant: {
                default:
                    'border-black/10 border bg-xq-primary text-xq-primary-foreground shadow-(--xq-button-highlight) hover:bg-xq-primary/90',
                secondary:
                    'bg-xq-secondary text-xq-secondary-foreground hover:bg-xq-secondary-hover',
                outline:
                    'border border-xq-border border-b-xq-input bg-xq-raised text-xq-foreground shadow-(--xq-button-shadow) hover:bg-xq-accent hover:text-xq-accent-foreground',
                ghost: 'text-xq-foreground hover:bg-xq-accent hover:text-xq-accent-foreground',
                destructive:
                    'border-black/10 border bg-xq-destructive text-xq-destructive-foreground shadow-(--xq-button-highlight) hover:bg-xq-destructive/90',
                link: 'text-xq-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-10 px-4 has-[>svg:first-child]:ps-3 has-[>svg:last-child]:pe-3',
                sm: 'h-8 gap-1.5 px-3 rounded-[calc(var(--xq-button-radius)-2px)]',
                lg: 'h-11 px-6',
                icon: 'size-10',
                'icon-sm': 'size-8 rounded-[calc(var(--xq-button-radius)-2px)]',
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
