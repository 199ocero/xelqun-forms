<script setup lang="ts">
import { gridStyle } from '../../composables/layout';
import type { SchemaNode } from '../../schema/types';
import XqNode from '../XqNode.vue';

defineProps<{ node: SchemaNode }>();
</script>

<template>
    <fieldset class="px-4 pt-2 pb-4 rounded-xq-lg border border-xq-border">
        <legend v-if="node.label" class="px-1.5 text-sm font-medium">{{ node.label }}</legend>
        <div class="xq-grid mt-2" :style="gridStyle(node.props?.columns)">
            <XqNode v-for="child in node.children ?? []" :key="child.key" :node="child" />
        </div>
    </fieldset>
</template>
