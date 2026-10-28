<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';
import { controlClass } from './classes';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
const model = defineModel<string | null>();
</script>

<template>
    <textarea
        v-model="model"
        data-slot="xq-textarea"
        :class="cn(controlClass, 'min-h-16 px-3 py-2', props.class)"
    />
</template>
