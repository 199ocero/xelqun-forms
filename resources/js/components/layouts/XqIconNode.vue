<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { iconSizeClasses, toneClasses } from '../../ui/classes';
import XqIcon from '../../ui/XqIcon.vue';

const props = defineProps<{ node: SchemaNode }>();
const options = computed(() => props.node.props ?? {});
const accessibleName = computed(
    () => props.node.extraAttributes?.['aria-label'] ?? options.value.tooltip,
);
</script>

<template>
    <span
        class="inline-flex"
        :role="accessibleName ? 'img' : undefined"
        :aria-label="accessibleName"
        :title="options.tooltip"
    >
        <XqIcon
            :name="options.icon"
            :class="
                cn(
                    iconSizeClasses[options.size] ?? 'size-5',
                    toneClasses[options.color] ?? 'text-xq-muted-foreground',
                )
            "
        />
    </span>
</template>
