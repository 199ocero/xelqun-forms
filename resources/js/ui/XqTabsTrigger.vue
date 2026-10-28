<script setup lang="ts">
import { TabsTrigger } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const props = defineProps<{
    value: string | number;
    disabled?: boolean;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <TabsTrigger
        data-slot="xq-tabs-trigger"
        :value="value"
        :disabled="disabled"
        :class="
            cn(
                'gap-1.5 px-3 text-sm font-medium data-[state=active]:shadow-sm inline-flex h-full cursor-pointer items-center justify-center rounded-xq-md whitespace-nowrap transition-[color,box-shadow] outline-none hover:text-xq-foreground focus-visible:ring-[3px] focus-visible:ring-xq-ring/30 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-xq-background data-[state=active]:text-xq-foreground',
                props.class,
            )
        "
    >
        <slot />
    </TabsTrigger>
</template>
