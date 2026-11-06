<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import XqIcon from '../../ui/XqIcon.vue';
import XqNode from '../XqNode.vue';

const props = defineProps<{ node: SchemaNode }>();
const options = computed(() => props.node.props ?? {});
</script>

<template>
    <div
        :class="
            cn(
                'gap-3 p-8 flex flex-col items-center text-center',
                options.contained && 'rounded-xq-lg border border-dashed border-xq-border',
            )
        "
    >
        <span
            v-if="options.icon"
            class="size-10 flex items-center justify-center rounded-full bg-xq-muted text-xq-muted-foreground"
        >
            <XqIcon :name="options.icon" class="size-5" />
        </span>
        <div class="gap-1 grid">
            <p class="text-sm font-semibold">{{ node.label }}</p>
            <p v-if="options.description" class="text-sm text-xq-muted-foreground">
                {{ options.description }}
            </p>
        </div>
        <div v-if="node.children?.length" class="gap-2 flex flex-wrap items-center justify-center">
            <XqNode v-for="child in node.children" :key="child.key" :node="child" />
        </div>
    </div>
</template>
