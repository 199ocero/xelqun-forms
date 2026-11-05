<script setup lang="ts">
import { Check, ChevronsUpDown, LoaderCircle, X } from 'lucide-vue-next';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed, reactive, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';
import type { SelectOption } from '../schema/types';
import { controlClass, optionClass, popoverClass, spinnerClass } from './classes';

type Value = string | number;

const props = defineProps<{
    id?: string;
    options: SelectOption[];
    multiple?: boolean;
    remote?: boolean;
    loading?: boolean;
    searching?: boolean;
    placeholder?: string | null;
    disabled?: boolean;
    invalid?: boolean;
    describedBy?: string;
    searchFailed?: boolean;
    class?: HTMLAttributes['class'];
}>();
const emit = defineEmits<{ search: [term: string]; blur: [] }>();
const model = defineModel<Value | Value[] | null>();
const searchTerm = ref('');
const labels = reactive(new Map<string, string>());

watch(
    () => props.options,
    (options) => options.forEach((option) => labels.set(String(option.value), option.label)),
    { immediate: true, deep: true },
);

const selectedValues = computed<Value[]>(() => {
    if (Array.isArray(model.value)) {
        return model.value;
    }

    return model.value === null || model.value === undefined || model.value === ''
        ? []
        : [model.value];
});

function labelFor(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return labels.get(String(value)) ?? String(value);
}

function remove(value: Value): void {
    model.value = selectedValues.value.filter((item) => item !== value);
}

function clear(): void {
    model.value = props.multiple ? [] : null;
    searchTerm.value = '';
}
</script>

<template>
    <ComboboxRoot
        v-model="model as any"
        :multiple="multiple"
        :disabled="disabled"
        :ignore-filter="remote"
        open-on-click
        :reset-search-term-on-blur="!multiple"
        @update:open="(open) => !open && emit('blur')"
    >
        <ComboboxAnchor
            data-slot="xq-combobox"
            :class="
                cn(
                    controlClass,
                    'min-h-9 gap-1 py-0.5 pr-1.5 pl-2 flex flex-wrap items-center has-[input:focus-visible]:border-xq-ring has-[input:focus-visible]:ring-[3px] has-[input:focus-visible]:ring-xq-ring/25',
                    invalid && 'border-xq-destructive',
                    props.class,
                )
            "
        >
            <template v-if="multiple">
                <span
                    v-for="value in selectedValues"
                    :key="String(value)"
                    class="gap-1 py-0.5 pr-1 pl-2 text-xs font-medium inline-flex items-center rounded-xq-sm bg-xq-secondary text-xq-secondary-foreground"
                >
                    {{ labelFor(value) }}
                    <button
                        type="button"
                        class="rounded-sm cursor-pointer opacity-60 hover:opacity-100"
                        :aria-label="`Remove ${labelFor(value)}`"
                        :disabled="disabled"
                        @click.stop="remove(value)"
                    >
                        <X class="size-3" />
                    </button>
                </span>
            </template>
            <ComboboxInput
                :id="id"
                v-model="searchTerm"
                @input="emit('search', ($event.target as HTMLInputElement).value)"
                :display-value="multiple ? undefined : labelFor"
                :placeholder="selectedValues.length && multiple ? '' : (placeholder ?? 'Search…')"
                :aria-invalid="invalid || undefined"
                :aria-describedby="describedBy"
                class="h-7 min-w-16 px-1 text-sm flex-1 bg-transparent outline-none placeholder:text-xq-muted-foreground disabled:cursor-not-allowed"
            />
            <LoaderCircle v-if="loading || searching" :class="spinnerClass" aria-hidden="true" />
            <button
                v-else-if="selectedValues.length && !disabled"
                type="button"
                class="size-6 rounded-sm flex cursor-pointer items-center justify-center text-xq-muted-foreground hover:text-xq-foreground"
                aria-label="Clear selection"
                @click.stop="clear"
            >
                <X class="size-3.5" />
            </button>
            <ComboboxTrigger
                class="size-6 flex cursor-pointer items-center justify-center text-xq-muted-foreground"
            >
                <ChevronsUpDown class="size-4" />
            </ComboboxTrigger>
        </ComboboxAnchor>
        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side-offset="4"
                :class="cn(popoverClass, 'max-h-72 min-w-40 w-(--reka-combobox-trigger-width)')"
            >
                <ComboboxViewport class="p-1">
                    <p
                        v-if="searchFailed"
                        role="status"
                        class="px-2 py-1.5 text-xs text-xq-destructive"
                    >
                        The search failed. Type again to retry.
                    </p>
                    <ComboboxEmpty class="px-2 py-6 text-sm text-center text-xq-muted-foreground">
                        {{
                            searching
                                ? 'Searching…'
                                : loading
                                  ? 'Loading…'
                                  : searchTerm || !remote
                                    ? 'No results'
                                    : 'Type to search'
                        }}
                    </ComboboxEmpty>
                    <ComboboxItem
                        v-for="option in options"
                        :key="String(option.value)"
                        :value="option.value"
                        :text-value="option.label"
                        :class="optionClass"
                    >
                        <span class="truncate">{{ option.label }}</span>
                        <ComboboxItemIndicator
                            class="right-2 size-3.5 absolute flex items-center justify-center"
                        >
                            <Check class="size-4" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
