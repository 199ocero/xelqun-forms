<script setup lang="ts">
import { computed } from 'vue';
import { useFormStore } from '../composables/context';
import { cn } from '../lib/utils';
import type { SchemaNode } from '../schema/types';
import { toneClasses } from '../ui/classes';
import XqIcon from '../ui/XqIcon.vue';

const props = defineProps<{ node: SchemaNode }>();
const store = useFormStore();
const hint = computed(() => store.text(props.node, 'hint'));
</script>

<template>
    <span
        v-if="hint || node.hintIcon"
        :class="
            cn(
                'gap-1 text-xs ms-auto flex items-center',
                toneClasses[node.hintColor ?? ''] ?? 'text-xq-muted-foreground',
            )
        "
        data-xq-field-hint
    >
        {{ hint }}
        <span
            v-if="node.hintIcon"
            :title="node.hintIconTooltip"
            :role="node.hintIconTooltip ? 'img' : undefined"
            :aria-label="node.hintIconTooltip"
        >
            <XqIcon :name="node.hintIcon" class="size-3.5" />
        </span>
    </span>
</template>
