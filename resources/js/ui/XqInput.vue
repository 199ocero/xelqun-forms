<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';
import { controlClass } from './classes';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
const model = defineModel<string | number | null>();
</script>

<template>
    <input
        v-model="model"
        data-slot="xq-input"
        :class="cn(controlClass, 'h-10 px-3 py-1', props.class)"
    />
</template>
