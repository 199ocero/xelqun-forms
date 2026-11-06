<script setup lang="ts">
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import XqNode from '../XqNode.vue';

defineProps<{ node: SchemaNode }>();

const alignments: Record<string, string> = {
    start: 'justify-start',
    center: 'justify-center',
    end: 'justify-end',
};
</script>

<template>
    <div :class="cn('gap-2 flex flex-wrap items-center', alignments[node.props?.alignment])">
        <XqNode v-for="child in node.children ?? []" :key="child.key" :node="child" />
    </div>
</template>
