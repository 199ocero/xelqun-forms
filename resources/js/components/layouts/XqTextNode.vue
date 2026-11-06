<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { textSizeClasses, toneClasses } from '../../ui/classes';
import XqBadge from '../../ui/XqBadge.vue';
import XqIcon from '../../ui/XqIcon.vue';

const props = defineProps<{ node: SchemaNode }>();
const options = computed(() => props.node.props ?? {});

const weights: Record<string, string> = {
    thin: 'font-thin',
    light: 'font-light',
    normal: 'font-normal',
    medium: 'font-medium',
    semibold: 'font-semibold',
    bold: 'font-bold',
    black: 'font-black',
};
const families: Record<string, string> = {
    sans: 'font-sans',
    serif: 'font-serif',
    mono: 'font-mono',
};
const classes = computed(() =>
    cn(
        textSizeClasses[options.value.size] ?? 'text-sm',
        toneClasses[options.value.color] ?? 'text-xq-muted-foreground',
        weights[options.value.weight],
        families[options.value.fontFamily],
    ),
);
</script>

<template>
    <XqBadge v-if="options.badge" variant="secondary" :class="classes" :title="options.tooltip">
        <XqIcon v-if="options.icon" :name="options.icon" class="size-3" />
        {{ options.content }}
    </XqBadge>
    <!-- Only an Htmlable from PHP lands here, so the form's author vouched for it. -->
    <div
        v-else-if="options.html !== null && options.html !== undefined"
        :class="classes"
        :title="options.tooltip"
        v-html="options.html"
    />
    <p v-else :class="classes" :title="options.tooltip">{{ options.content }}</p>
</template>
