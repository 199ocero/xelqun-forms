<script setup lang="ts">
import { TabsRoot } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class']; unmountOnHide?: boolean }>();
const model = defineModel<string | number>();
</script>

<template>
    <TabsRoot
        v-model="model"
        data-slot="xq-tabs"
        :unmount-on-hide="unmountOnHide ?? true"
        :class="cn('gap-3 flex flex-col', props.class)"
    >
        <slot />
    </TabsRoot>
</template>
