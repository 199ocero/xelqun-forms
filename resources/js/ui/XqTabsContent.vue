<script setup lang="ts">
import { TabsContent } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const props = defineProps<{ value: string | number; class?: HTMLAttributes['class'] }>();
</script>

<template>
    <TabsContent
        data-slot="xq-tabs-content"
        :value="value"
        :class="cn('outline-none', props.class)"
    >
        <slot />
    </TabsContent>
</template>
