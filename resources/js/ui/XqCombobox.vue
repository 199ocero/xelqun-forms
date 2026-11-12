<script setup lang="ts">
import { Check, ChevronLeft, ChevronRight, ChevronsUpDown, LoaderCircle, X } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxLabel,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed, reactive, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { useSortable } from '../composables/useSortable';
import { cn } from '../lib/utils';
import type { SelectMessages, SelectOption } from '../schema/types';
import { controlClass, groupLabelClass, optionClass, popoverClass, spinnerClass } from './classes';
import XqMenuGlide from './XqMenuGlide.vue';
import { groupOptions, moveItem, OPTION_LIMIT } from './options';
import { useTranslator } from '../composables/context';

const t = useTranslator();
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
    messages?: SelectMessages;
    reorderable?: boolean;
    /** The most options drawn at once. */
    limit?: number;
    class?: HTMLAttributes['class'];
}>();
const limit = computed(() => props.limit ?? OPTION_LIMIT);
const emit = defineEmits<{ search: [term: string]; blur: [] }>();
const model = defineModel<Value | Value[] | null>();
const searchTerm = ref('');
/** What was typed, apart from the label the input shows for a choice. */
const typed = ref('');
const labels = reactive(new Map<string, string>());

// Filtered here rather than by the combobox, so only the first matches are drawn.
const matches = computed(() => {
    const term = typed.value.trim().toLocaleLowerCase();

    return props.remote || term === ''
        ? props.options
        : props.options.filter((option) => option.label.toLocaleLowerCase().includes(term));
});
const groups = computed(() => groupOptions(matches.value.slice(0, limit.value)));
const hiddenCount = computed(() => Math.max(0, matches.value.length - limit.value));

function onInput(event: Event): void {
    typed.value = (event.target as HTMLInputElement).value;
    emit('search', typed.value);
}

function onOpenChange(open: boolean): void {
    if (!open) {
        typed.value = '';
        emit('blur');
    }
}

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

function move(value: Value, offset: number): void {
    model.value = moveItem(selectedValues.value, value, offset);
}

const chipList = ref<HTMLElement | null>(null);

useSortable(chipList, {
    draggable: '> [data-xq-chip]',
    enabled: () => !!props.reorderable && !props.disabled && selectedValues.value.length > 1,
    onMove: (from, to) => move(selectedValues.value[from], to - from),
});

function clear(): void {
    model.value = props.multiple ? [] : null;
    searchTerm.value = '';
    typed.value = '';
}
</script>

<template>
    <ComboboxRoot
        v-model="model as any"
        :multiple="multiple"
        :disabled="disabled"
        ignore-filter
        open-on-click
        :reset-search-term-on-blur="!multiple"
        @update:open="onOpenChange"
    >
        <ComboboxAnchor
            data-slot="xq-combobox"
            :class="
                cn(
                    controlClass,
                    'min-h-10 gap-1 py-0.5 pr-1.5 pl-2 flex flex-wrap items-center has-[input:focus-visible]:border-xq-ring has-[input:focus-visible]:ring-[3px] has-[input:focus-visible]:ring-xq-ring/25',
                    invalid && 'border-xq-destructive',
                    props.class,
                )
            "
        >
            <span v-if="multiple" ref="chipList" class="gap-1 flex max-w-full flex-wrap">
                <span
                    v-for="(value, index) in selectedValues"
                    :key="String(value)"
                    data-xq-chip
                    :class="
                        cn(
                            'gap-1 py-0.5 pr-1 pl-2 text-xs font-medium inline-flex items-center rounded-xq-sm bg-xq-secondary text-xq-secondary-foreground',
                            reorderable && 'cursor-grab touch-none',
                        )
                    "
                >
                    <button
                        v-if="reorderable && index > 0"
                        type="button"
                        class="rounded-sm cursor-pointer opacity-60 hover:opacity-100"
                        :aria-label="t('Move :name earlier', { name: labelFor(value) })"
                        :disabled="disabled"
                        @click.stop="move(value, -1)"
                    >
                        <ChevronLeft class="size-3" />
                    </button>
                    {{ labelFor(value) }}
                    <button
                        v-if="reorderable && index < selectedValues.length - 1"
                        type="button"
                        class="rounded-sm cursor-pointer opacity-60 hover:opacity-100"
                        :aria-label="t('Move :name later', { name: labelFor(value) })"
                        :disabled="disabled"
                        @click.stop="move(value, 1)"
                    >
                        <ChevronRight class="size-3" />
                    </button>
                    <button
                        type="button"
                        class="rounded-sm cursor-pointer opacity-60 hover:opacity-100"
                        :aria-label="t('Remove :name', { name: labelFor(value) })"
                        :disabled="disabled"
                        @click.stop="remove(value)"
                    >
                        <X class="size-3" />
                    </button>
                </span>
            </span>
            <ComboboxInput
                :id="id"
                v-model="searchTerm"
                @input="onInput"
                :display-value="multiple ? undefined : labelFor"
                :placeholder="
                    selectedValues.length && multiple ? '' : (placeholder ?? t('Search…'))
                "
                :aria-invalid="invalid || undefined"
                :aria-describedby="describedBy"
                class="h-7 min-w-16 px-1 text-sm flex-1 bg-transparent outline-none placeholder:text-xq-muted-foreground disabled:cursor-not-allowed"
            />
            <LoaderCircle v-if="loading || searching" :class="spinnerClass" aria-hidden="true" />
            <button
                v-else-if="selectedValues.length && !disabled"
                type="button"
                class="size-6 rounded-sm flex cursor-pointer items-center justify-center text-xq-muted-foreground hover:text-xq-foreground"
                :aria-label="t('Clear selection')"
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
                <ComboboxViewport class="p-1 relative">
                    <XqMenuGlide />
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
                                ? (messages?.searching ?? t('Searching…'))
                                : loading
                                  ? (messages?.loading ?? t('Loading…'))
                                  : searchTerm
                                    ? (messages?.noResults ?? t('No results'))
                                    : remote
                                      ? (messages?.prompt ?? t('Type to search'))
                                      : (messages?.noOptions ?? t('No results'))
                        }}
                    </ComboboxEmpty>
                    <ComboboxGroup v-for="group in groups" :key="group.label ?? ''">
                        <ComboboxLabel v-if="group.label" :class="groupLabelClass">{{
                            group.label
                        }}</ComboboxLabel>
                        <ComboboxItem
                            v-for="option in group.options"
                            :key="String(option.value)"
                            :value="option.value"
                            :text-value="option.label"
                            :disabled="option.disabled"
                            :class="optionClass"
                        >
                            <!-- The server cleaned it, as for a rich editor. -->
                            <span v-if="option.html" class="truncate" v-html="option.html" />
                            <span v-else class="truncate">{{ option.label }}</span>
                            <ComboboxItemIndicator
                                class="right-2 size-3.5 absolute flex items-center justify-center"
                            >
                                <Check class="size-4" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>
                    </ComboboxGroup>
                    <p
                        v-if="hiddenCount > 0"
                        role="status"
                        class="px-2 py-1.5 text-xs text-xq-muted-foreground"
                        data-xq-more-options
                    >
                        {{
                            t('Showing :shown of :total. Type to narrow it down.', {
                                shown: limit,
                                total: matches.length,
                            })
                        }}
                    </p>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
