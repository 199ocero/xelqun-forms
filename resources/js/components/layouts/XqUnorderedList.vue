<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { textSizeClasses } from '../../ui/classes';
import XqNode from '../XqNode.vue';

const props = defineProps<{ node: SchemaNode }>();
const size = computed(() => textSizeClasses[props.node.props?.size] ?? 'text-sm');
</script>

<template>
    <ul :class="cn('gap-1 ps-5 grid list-disc text-xq-muted-foreground', size)">
        <li v-for="child in node.children ?? []" :key="child.key">
            <XqNode :node="child" />
        </li>
    </ul>
</template>
