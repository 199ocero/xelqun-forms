<script setup lang="ts">
import { TabsList } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';
import XqTabGlide from './XqTabGlide.vue';

const props = defineProps<{ class?: HTMLAttributes['class']; vertical?: boolean }>();
</script>

<template>
    <TabsList
        data-slot="xq-tabs-list"
        :class="
            cn(
                'gap-1 p-1 relative inline-flex w-fit max-w-full [scrollbar-width:none] overflow-x-auto rounded-xq-lg bg-xq-muted text-xq-muted-foreground',
                vertical
                    ? 'sm:*:h-8 h-9 sm:h-auto sm:flex-col sm:items-stretch items-center'
                    : 'h-9 items-center',
                props.class,
            )
        "
    >
        <XqTabGlide
            :vertical="vertical"
            :class="cn('shadow-sm rounded-xq-md bg-xq-background', !vertical && 'inset-y-1')"
            data-slot="xq-tabs-indicator"
        />
        <slot />
    </TabsList>
</template>
