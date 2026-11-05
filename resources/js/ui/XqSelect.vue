<script setup lang="ts">
import { Check, ChevronDown } from 'lucide-vue-next';
import {
    SelectContent,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';
import type { SelectOption } from '../schema/types';
import { controlClass, optionClass, popoverClass } from './classes';

const props = defineProps<{
    id?: string;
    options: SelectOption[];
    placeholder?: string | null;
    disabled?: boolean;
    invalid?: boolean;
    describedBy?: string;
    class?: HTMLAttributes['class'];
}>();
const model = defineModel<string | number | null>();
</script>

<template>
    <SelectRoot v-model="model" :disabled="disabled">
        <SelectTrigger
            :id="id"
            data-slot="xq-select"
            :aria-invalid="invalid || undefined"
            :aria-describedby="describedBy"
            :class="
                cn(
                    controlClass,
                    'h-9 gap-2 px-3 py-1 flex cursor-pointer items-center justify-between text-left data-[placeholder]:text-xq-muted-foreground',
                    props.class,
                )
            "
        >
            <SelectValue :placeholder="placeholder ?? 'Select an option'" class="truncate" />
            <ChevronDown class="size-4 shrink-0 opacity-60" />
        </SelectTrigger>
        <SelectPortal>
            <SelectContent
                position="popper"
                :side-offset="4"
                :class="cn(popoverClass, 'max-h-72 min-w-32 w-(--reka-select-trigger-width)')"
            >
                <SelectViewport class="p-1">
                    <SelectItem
                        v-for="option in options"
                        :key="String(option.value)"
                        :value="option.value"
                        :class="optionClass"
                    >
                        <SelectItemText>{{ option.label }}</SelectItemText>
                        <SelectItemIndicator
                            class="right-2 size-3.5 absolute flex items-center justify-center"
                        >
                            <Check class="size-4" />
                        </SelectItemIndicator>
                    </SelectItem>
                    <div
                        v-if="options.length === 0"
                        class="px-2 py-6 text-sm text-center text-xq-muted-foreground"
                    >
                        No options
                    </div>
                </SelectViewport>
            </SelectContent>
        </SelectPortal>
    </SelectRoot>
</template>
