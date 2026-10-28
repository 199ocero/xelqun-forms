<script setup lang="ts">
import { computed } from 'vue';
import { useFormStore } from '../composables/context';
import { spanStyle } from '../composables/layout';
import { useRegistry } from '../composables/registry';
import type { SchemaNode } from '../schema/types';
import XqUnknownNode from './XqUnknownNode.vue';

const props = defineProps<{ node: SchemaNode }>();
const store = useFormStore();
const registry = useRegistry();

const visible = computed(() => props.node.type !== 'hidden' && store.isVisible(props.node));
const component = computed(() => registry?.get(props.node.type) ?? null);
</script>

<template>
    <div v-if="visible" class="xq-cell" :style="spanStyle(node.span)" :data-xq-key="node.key">
        <component :is="component" v-if="component" :node="node" />
        <XqUnknownNode v-else :node="node" />
    </div>
</template>
