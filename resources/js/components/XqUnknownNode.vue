<script setup lang="ts">
import type { SchemaNode } from '../schema/types';

defineProps<{ node: SchemaNode }>();
</script>

<template>
    <div
        class="px-3 py-2 text-xs rounded-xq-md border border-dashed border-xq-destructive/50 bg-xq-destructive/5 text-xq-destructive"
    >
        No component is registered for type
        <code class="font-mono font-semibold">{{ node.type }}</code> ({{ node.key }}). Register one
        with <code class="font-mono">createXelqunForms({ components })</code>.
    </div>
</template>
