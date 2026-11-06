<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';

const props = defineProps<{ node: SchemaNode }>();
const options = computed(() => props.node.props ?? {});

const alignments: Record<string, string> = {
    start: 'justify-start',
    center: 'justify-center',
    end: 'justify-end',
};

function cssSize(size: unknown): string | undefined {
    return typeof size === 'number' ? `${size}px` : (size as string | undefined);
}
</script>

<template>
    <div :class="cn('flex', alignments[options.alignment])">
        <img
            :src="options.url"
            :alt="options.alt"
            :title="options.tooltip"
            :style="{ width: cssSize(options.width), height: cssSize(options.height) }"
            class="max-w-full rounded-xq-md"
        />
    </div>
</template>
