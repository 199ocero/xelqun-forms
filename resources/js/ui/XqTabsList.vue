<script setup lang="ts">
import { TabsIndicator, TabsList } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <TabsList
        data-slot="xq-tabs-list"
        :class="
            cn(
                'h-9 gap-1 p-1 relative inline-flex w-fit max-w-full items-center overflow-x-auto rounded-xq-lg bg-xq-muted text-xq-muted-foreground',
                props.class,
            )
        "
    >
        <TabsIndicator
            class="inset-y-1 left-0 absolute w-(--reka-tabs-indicator-size) translate-x-(--reka-tabs-indicator-position) transition-[width,translate] duration-(--xq-duration-slow) ease-(--xq-ease-out)"
            data-slot="xq-tabs-indicator"
        >
            <span class="shadow-sm block size-full rounded-xq-md bg-xq-background"></span>
        </TabsIndicator>
        <slot />
    </TabsList>
</template>
