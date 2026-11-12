<script setup lang="ts">
import { parseTime } from '@internationalized/date';
import type { Time } from '@internationalized/date';
import { TimeFieldInput, TimeFieldRoot } from 'reka-ui';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';
import { controlClass } from './classes';

const props = defineProps<{
    id?: string;
    seconds?: boolean;
    steps?: { hour: number; minute: number; second: number };
    disabled?: boolean;
    invalid?: boolean;
    describedBy?: string;
    label?: string | null;
    locale?: string;
    class?: HTMLAttributes['class'];
}>();
const model = defineModel<string | null>();

const time = computed<Time | undefined>({
    get() {
        try {
            return model.value ? parseTime(model.value) : undefined;
        } catch {
            return undefined;
        }
    },
    set(next) {
        model.value = next ? next.toString().slice(0, props.seconds ? 8 : 5) : null;
    },
});
</script>

<template>
    <TimeFieldRoot
        :id="id"
        v-slot="{ segments }"
        v-model="time"
        :granularity="seconds ? 'second' : 'minute'"
        :step="steps"
        :hour-cycle="24"
        :locale="locale"
        :disabled="disabled"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedBy"
        :aria-label="label ?? undefined"
        data-slot="xq-time-field"
        :class="cn(controlClass, 'h-10 px-3 flex items-center tabular-nums', props.class)"
    >
        <template v-for="segment in segments" :key="segment.part">
            <TimeFieldInput v-if="segment.part === 'literal'" :part="segment.part">
                {{ segment.value }}
            </TimeFieldInput>
            <TimeFieldInput
                v-else
                :part="segment.part"
                class="px-0.5 rounded-sm outline-none focus:bg-xq-accent focus:text-xq-accent-foreground data-[placeholder]:text-xq-muted-foreground"
            >
                {{ segment.value }}
            </TimeFieldInput>
        </template>
    </TimeFieldRoot>
</template>
