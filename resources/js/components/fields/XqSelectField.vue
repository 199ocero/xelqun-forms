<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useField } from '../../composables/useField';
import type { SchemaNode, SelectOption } from '../../schema/types';
import XqCombobox from '../../ui/XqCombobox.vue';
import XqSelect from '../../ui/XqSelect.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';

const props = defineProps<{ node: SchemaNode }>();
const { store, path, value, error, required, disabled, id, onBlur } = useField(props.node);
const options = computed<SelectOption[]>(() => props.node.props?.options ?? []);
const useCombobox = computed(() => !!(props.node.props?.searchable || props.node.props?.multiple));
const isRemote = computed(() => !!props.node.props?.async);

const remoteOptions = ref<SelectOption[]>([]);
const loading = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;

watch(options, (next) => (remoteOptions.value = mergeOptions(next, remoteOptions.value)), {
    immediate: true,
});

function mergeOptions(first: SelectOption[], second: SelectOption[]): SelectOption[] {
    const seen = new Set(first.map((option) => String(option.value)));

    return [...first, ...second.filter((option) => !seen.has(String(option.value)))];
}

function onSearch(term: string): void {
    if (!isRemote.value) {
        return;
    }

    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(async () => {
        controller?.abort();
        controller = new AbortController();
        loading.value = true;

        try {
            const results = await store.search(path, term, controller.signal);
            remoteOptions.value = mergeOptions(results, options.value);
        } catch (searchError) {
            if (!controller.signal.aborted) {
                console.error('[xelqun] Search failed.', searchError);
            }
        } finally {
            loading.value = false;
        }
    }, 250);
}

onBeforeUnmount(() => {
    controller?.abort();

    if (timer) {
        clearTimeout(timer);
    }
});

const hasValue = computed(
    () => value.value !== null && value.value !== undefined && value.value !== '',
);
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required">
        <XqCombobox
            v-if="useCombobox"
            :id="id"
            v-model="value"
            :options="isRemote ? remoteOptions : options"
            :multiple="!!node.props?.multiple"
            :remote="isRemote"
            :loading="loading"
            :placeholder="node.placeholder"
            :disabled="disabled"
            :invalid="!!error"
            @search="onSearch"
            @blur="onBlur"
        />
        <div v-else class="relative">
            <XqSelect
                :id="id"
                v-model="value"
                :options="options"
                :placeholder="node.placeholder"
                :disabled="disabled"
                :invalid="!!error"
                :class="!required && hasValue ? '[&>span:first-child]:pr-6' : undefined"
            />
            <button
                v-if="!required && hasValue && !disabled"
                type="button"
                class="right-9 size-5 rounded-sm absolute top-1/2 flex -translate-y-1/2 cursor-pointer items-center justify-center text-xq-muted-foreground hover:text-xq-foreground"
                aria-label="Clear selection"
                @click="value = null"
            >
                <X class="size-3.5" />
            </button>
        </div>
    </XqFieldWrapper>
</template>
