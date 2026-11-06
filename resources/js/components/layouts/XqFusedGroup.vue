<script setup lang="ts">
import { computed, provide } from 'vue';
import { FUSED_GROUP, useFormStore } from '../../composables/context';
import { gridStyle } from '../../composables/layout';
import { fieldId } from '../../composables/useField';
import { eachNode } from '../../core/patch';
import type { SchemaNode } from '../../schema/types';
import XqNode from '../XqNode.vue';

const props = defineProps<{ node: SchemaNode }>();
const store = useFormStore();
const fields = computed(() => {
    const found: SchemaNode[] = [];
    eachNode(props.node.children ?? [], (child) => child.path && found.push(child));

    return found;
});
const required = computed(() => fields.value.some((field) => store.isRequired(field)));
const errors = computed(() =>
    fields.value.flatMap((field) =>
        store.errors[field.path!]
            ? [{ id: `${fieldId(store, field.path!)}-error`, message: store.errors[field.path!] }]
            : [],
    ),
);

provide(FUSED_GROUP, true);
</script>

<template>
    <div class="gap-2 grid" role="group" :aria-label="node.label ?? undefined">
        <p v-if="node.label" class="gap-0.5 text-sm font-medium flex leading-none">
            {{ node.label }}
            <span v-if="required" class="text-xq-destructive" aria-hidden="true">*</span>
        </p>
        <div class="xq-grid xq-fused" :style="gridStyle(node.props?.columns)">
            <XqNode v-for="child in node.children ?? []" :key="child.key" :node="child" />
        </div>
        <p
            v-for="item in errors"
            :id="item.id"
            :key="item.id"
            class="text-xs font-medium text-xq-destructive"
        >
            {{ item.message }}
        </p>
    </div>
</template>
