<script setup lang="ts">
import { SwitchRoot, SwitchThumb } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const props = defineProps<{ id?: string; disabled?: boolean; class?: HTMLAttributes['class'] }>();
const model = defineModel<boolean>({ default: false });
</script>

<template>
    <SwitchRoot
        :id="id"
        v-model="model"
        data-slot="xq-switch"
        :disabled="disabled"
        :class="
            cn(
                'peer h-5 w-9 shadow-xs inline-flex shrink-0 cursor-pointer items-center rounded-full border border-transparent transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-xq-ring/30 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-xq-primary data-[state=unchecked]:bg-xq-input',
                props.class,
            )
        "
    >
        <SwitchThumb
            class="size-4 shadow-sm data-[state=unchecked]:translate-x-0.5 pointer-events-none block rounded-full bg-xq-background ring-0 transition-transform duration-(--xq-duration-slow) ease-(--xq-ease-spring) data-[state=checked]:translate-x-[calc(100%-1px)] motion-reduce:transition-none"
        />
    </SwitchRoot>
</template>
