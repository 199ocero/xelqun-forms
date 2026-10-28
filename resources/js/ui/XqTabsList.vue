<script setup lang="ts">
import { TabsList } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <TabsList
        data-slot="xq-tabs-list"
        :class="
            cn(
                'h-9 gap-1 p-1 inline-flex w-fit max-w-full items-center overflow-x-auto rounded-xq-lg bg-xq-muted text-xq-muted-foreground',
                props.class,
            )
        "
    >
        <slot />
    </TabsList>
</template>
