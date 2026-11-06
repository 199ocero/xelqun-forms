<script setup lang="ts">
import { computed } from 'vue';
import { useFormStore } from '../composables/context';
import type { FieldSlot, SchemaNode } from '../schema/types';
import XqNode from './XqNode.vue';

const props = defineProps<{ node: SchemaNode; name: FieldSlot }>();
const store = useFormStore();
const nodes = computed(() =>
    (props.node.children ?? []).filter(
        (child) => child.slot === props.name && store.isVisible(child),
    ),
);
</script>

<template>
    <div v-if="nodes.length" class="gap-2 flex flex-wrap items-center">
        <XqNode v-for="child in nodes" :key="child.key" :node="child" />
    </div>
</template>
