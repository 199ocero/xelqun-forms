<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useField } from '../../composables/useField';
import type { SchemaNode, SelectOption } from '../../schema/types';
import XqCombobox from '../../ui/XqCombobox.vue';
import XqSelect from '../../ui/XqSelect.vue';
import { OPTION_LIMIT } from '../../ui/options';
import XqFieldWrapper from '../XqFieldWrapper.vue';
import { useTranslator } from '../../composables/context';

const t = useTranslator();
const props = defineProps<{ node: SchemaNode }>();
const {
    store,
    path,
    value,
    error,
    placeholder,
    describedBy,
    showLoading,
    required,
    disabled,
    id,
    onBlur,
} = useField(props.node);
const options = computed<SelectOption[]>(() => props.node.props?.options ?? []);
// A long list gets a search box, since nobody can scroll to an option among thousands.
const useCombobox = computed(
    () =>
        !!(props.node.props?.searchable || props.node.props?.multiple) ||
        options.value.length > (props.node.props?.optionsLimit ?? OPTION_LIMIT),
);
const isRemote = computed(() => !!props.node.props?.async);

const remoteOptions = ref<SelectOption[]>([]);
const searching = ref(false);
const searchFailed = ref(false);
const debounce = computed<number>(() => props.node.props?.searchDebounce ?? 250);
const cache = new Map<string, SelectOption[]>();
let lastTerm = '';
let timer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;

watch(options, (next) => (remoteOptions.value = mergeOptions(next, remoteOptions.value)), {
    immediate: true,
});

function mergeOptions(first: SelectOption[], second: SelectOption[]): SelectOption[] {
    const seen = new Set(first.map((option) => String(option.value)));

    return [...first, ...second.filter((option) => !seen.has(String(option.value)))];
}

function cancelPendingSearch(): void {
    if (timer) {
        clearTimeout(timer);
        timer = null;
    }

    controller?.abort();
    searching.value = false;
}

/**
 * Called only when the user types (not when the input text is reset after a
 * selection, a clear or a blur). Waits for a pause in typing, skips empty and
 * repeated terms, and reuses results it has already fetched.
 */
function onSearch(input: string): void {
    if (!isRemote.value) {
        return;
    }

    const term = input.trim();

    cancelPendingSearch();
    searchFailed.value = false;

    if (term === '') {
        lastTerm = '';
        remoteOptions.value = options.value;

        return;
    }

    if (term === lastTerm) {
        return;
    }

    const cached = cache.get(term.toLowerCase());

    if (cached) {
        lastTerm = term;
        remoteOptions.value = mergeOptions(cached, options.value);

        return;
    }

    timer = setTimeout(async () => {
        timer = null;
        controller = new AbortController();
        searching.value = true;

        try {
            const results = await store.search(path, term, controller.signal);

            cache.set(term.toLowerCase(), results);
            lastTerm = term;
            remoteOptions.value = mergeOptions(results, options.value);
        } catch (searchError) {
            if (!controller.signal.aborted) {
                searchFailed.value = true;
                console.error('[xelqun] Search failed.', searchError);
            }
        } finally {
            searching.value = false;
        }
    }, debounce.value);
}

onBeforeUnmount(cancelPendingSearch);

const hasValue = computed(
    () => value.value !== null && value.value !== undefined && value.value !== '',
);
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required" control-shows-loading>
        <XqCombobox
            v-if="useCombobox"
            :id="id"
            v-model="value"
            :options="isRemote ? remoteOptions : options"
            :multiple="!!node.props?.multiple"
            :remote="isRemote"
            :loading="showLoading"
            :searching="searching"
            :placeholder="placeholder"
            :disabled="disabled"
            :invalid="!!error"
            :described-by="describedBy"
            :search-failed="searchFailed"
            :messages="node.props?.messages"
            :reorderable="node.props?.reorderable"
            :limit="node.props?.optionsLimit"
            @search="onSearch"
            @blur="onBlur"
        />
        <div v-else class="relative">
            <XqSelect
                :id="id"
                v-model="value"
                :options="options"
                :loading="showLoading"
                :placeholder="placeholder"
                :disabled="disabled"
                :invalid="!!error"
                :described-by="describedBy"
                :messages="node.props?.messages"
                :class="!required && hasValue ? '[&>span:first-child]:pr-6' : undefined"
            />
            <button
                v-if="!required && hasValue && !disabled"
                type="button"
                class="right-9 size-5 rounded-sm absolute top-1/2 flex -translate-y-1/2 cursor-pointer items-center justify-center text-xq-muted-foreground hover:text-xq-foreground"
                :aria-label="t('Clear selection')"
                @click="value = null"
            >
                <X class="size-3.5" />
            </button>
        </div>
    </XqFieldWrapper>
</template>
