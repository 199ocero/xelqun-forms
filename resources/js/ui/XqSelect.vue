<script setup lang="ts">
import { Check, ChevronDown, LoaderCircle } from 'lucide-vue-next';
import {
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectLabel,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from 'reka-ui';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';
import type { SelectMessages, SelectOption } from '../schema/types';
import { controlClass, groupLabelClass, optionClass, popoverClass, spinnerClass } from './classes';
import { groupOptions } from './options';
import { useTranslator } from '../composables/context';

const t = useTranslator();
const props = defineProps<{
    id?: string;
    options: SelectOption[];
    loading?: boolean;
    placeholder?: string | null;
    disabled?: boolean;
    invalid?: boolean;
    describedBy?: string;
    messages?: SelectMessages;
    class?: HTMLAttributes['class'];
}>();
const model = defineModel<string | number | null>();
const groups = computed(() => groupOptions(props.options));
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
            <SelectValue :placeholder="placeholder ?? t('Select an option')" class="truncate" />
            <LoaderCircle v-if="loading" :class="spinnerClass" aria-hidden="true" />
            <ChevronDown v-else class="size-4 shrink-0 opacity-60" />
        </SelectTrigger>
        <SelectPortal>
            <SelectContent
                position="popper"
                :side-offset="4"
                :class="cn(popoverClass, 'max-h-72 min-w-32 w-(--reka-select-trigger-width)')"
            >
                <SelectViewport class="p-1">
                    <SelectGroup v-for="group in groups" :key="group.label ?? ''">
                        <SelectLabel v-if="group.label" :class="groupLabelClass">{{
                            group.label
                        }}</SelectLabel>
                        <SelectItem
                            v-for="option in group.options"
                            :key="String(option.value)"
                            :value="option.value"
                            :disabled="option.disabled"
                            :class="optionClass"
                        >
                            <SelectItemText>{{ option.label }}</SelectItemText>
                            <SelectItemIndicator
                                class="right-2 size-3.5 absolute flex items-center justify-center"
                            >
                                <Check class="size-4" />
                            </SelectItemIndicator>
                        </SelectItem>
                    </SelectGroup>
                    <div
                        v-if="options.length === 0"
                        class="px-2 py-6 text-sm text-center text-xq-muted-foreground"
                    >
                        {{
                            loading
                                ? (messages?.loading ?? t('Loading…'))
                                : (messages?.noOptions ?? t('No options'))
                        }}
                    </div>
                </SelectViewport>
            </SelectContent>
        </SelectPortal>
    </SelectRoot>
</template>
