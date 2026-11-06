<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import XqNode from '../XqNode.vue';

const props = defineProps<{ node: SchemaNode }>();

const directions: Record<string, string> = {
    sm: 'flex-col sm:flex-row',
    md: 'flex-col md:flex-row',
    lg: 'flex-col lg:flex-row',
    xl: 'flex-col xl:flex-row',
    '2xl': 'flex-col 2xl:flex-row',
};
const direction = computed(() => directions[props.node.props?.from] ?? 'flex-row');
</script>

<template>
    <div :class="cn('xq-flex gap-4 flex', direction)">
        <XqNode v-for="child in node.children ?? []" :key="child.key" :node="child" />
    </div>
</template>
