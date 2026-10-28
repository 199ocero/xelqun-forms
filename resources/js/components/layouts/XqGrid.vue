<script setup lang="ts">
import { gridStyle } from '../../composables/layout';
import type { SchemaNode } from '../../schema/types';
import XqNode from '../XqNode.vue';

defineProps<{ node: SchemaNode }>();
</script>

<template>
    <div class="xq-grid" :style="gridStyle(node.props?.columns)">
        <XqNode v-for="child in node.children ?? []" :key="child.key" :node="child" />
    </div>
</template>
