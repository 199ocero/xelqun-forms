<script setup lang="ts">
import { SwitchRoot, SwitchThumb } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';
import XqIcon from './XqIcon.vue';

const props = defineProps<{
    id?: string;
    disabled?: boolean;
    class?: HTMLAttributes['class'];
    onIcon?: string;
    offIcon?: string;
    onColor?: string;
    offColor?: string;
}>();
const model = defineModel<boolean>({ default: false });

const tracks: Record<string, string> = {
    primary: 'bg-xq-primary',
    danger: 'bg-xq-destructive',
    info: 'bg-xq-info',
    success: 'bg-xq-success',
    warning: 'bg-xq-warning',
    gray: 'bg-xq-muted-foreground',
};

const icons: Record<string, string> = {
    primary: 'text-xq-primary',
    danger: 'text-xq-destructive',
    info: 'text-xq-info',
    success: 'text-xq-success',
    warning: 'text-xq-warning',
    gray: 'text-xq-muted-foreground',
};
</script>

<template>
    <SwitchRoot
        :id="id"
        v-model="model"
        data-slot="xq-switch"
        :disabled="disabled"
        :class="
            cn(
                'peer h-5 w-9 shadow-xs inline-flex shrink-0 cursor-pointer items-center rounded-full border border-transparent transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-xq-ring/30 disabled:cursor-not-allowed disabled:opacity-50',
                model
                    ? (tracks[onColor ?? ''] ?? tracks.primary)
                    : (tracks[offColor ?? ''] ?? 'bg-xq-input'),
                props.class,
            )
        "
    >
        <SwitchThumb
            class="size-4 shadow-sm data-[state=unchecked]:translate-x-0.5 pointer-events-none flex items-center justify-center rounded-full bg-xq-background ring-0 transition-transform duration-(--xq-duration-slow) ease-(--xq-ease-spring) data-[state=checked]:translate-x-[calc(100%-1px)] motion-reduce:transition-none"
        >
            <XqIcon
                v-if="model ? onIcon : offIcon"
                :name="(model ? onIcon : offIcon)!"
                :class="
                    cn(
                        'size-3',
                        model
                            ? (icons[onColor ?? ''] ?? icons.primary)
                            : (icons[offColor ?? ''] ?? icons.gray),
                    )
                "
            />
        </SwitchThumb>
    </SwitchRoot>
</template>
