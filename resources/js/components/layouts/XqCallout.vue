<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { toneClasses } from '../../ui/classes';
import XqIcon from '../../ui/XqIcon.vue';
import XqNode from '../XqNode.vue';

const props = defineProps<{ node: SchemaNode }>();
const options = computed(() => props.node.props ?? {});

const surfaces: Record<string, string> = {
    danger: 'border-xq-destructive/40 bg-xq-destructive/10',
    info: 'border-xq-info/40 bg-xq-info/10',
    success: 'border-xq-success/40 bg-xq-success/10',
    warning: 'border-xq-warning/40 bg-xq-warning/10',
};
</script>

<template>
    <div
        :class="
            cn(
                'gap-3 p-4 flex items-start rounded-xq-lg border border-xq-border bg-xq-card text-xq-card-foreground',
                surfaces[options.status],
            )
        "
    >
        <XqIcon
            v-if="options.icon"
            :name="options.icon"
            :class="cn('mt-0.5 size-5', toneClasses[options.status])"
        />
        <div class="gap-3 min-w-0 grid flex-1">
            <div class="gap-1 grid">
                <p v-if="node.label" class="text-sm font-semibold">{{ node.label }}</p>
                <p v-if="options.description" class="text-sm text-xq-muted-foreground">
                    {{ options.description }}
                </p>
            </div>
            <div v-if="node.children?.length" class="gap-2 flex flex-wrap items-center">
                <XqNode v-for="child in node.children" :key="child.key" :node="child" />
            </div>
        </div>
    </div>
</template>
