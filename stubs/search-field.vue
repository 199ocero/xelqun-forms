<script setup lang="ts">
import { useField, XqFieldWrapper, XqInput } from '@xelqun/forms';
import type { SchemaNode, SelectOption } from '@xelqun/forms';
import { computed, ref } from 'vue';

const props = defineProps<{ node: SchemaNode }>();
const { store, path, value, error, describedBy, required, disabled, id } = useField<string | null>(
    props.node,
);
const results = ref<SelectOption[]>([]);
const failed = ref(false);
const selected = computed(() =>
    [...(props.node.props?.options ?? []), ...results.value].find(
        (option: SelectOption) => String(option.value) === String(value.value),
    ),
);
let controller: AbortController | null = null;

async function search(input: string | number | null | undefined): Promise<void> {
    const term = String(input ?? '').trim();

    controller?.abort();
    controller = new AbortController();
    failed.value = false;

    try {
        results.value = term === '' ? [] : await store.search(path, term, controller.signal);
    } catch {
        failed.value = !controller.signal.aborted;
    }
}
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required">
        <XqInput
            :id="id"
            :disabled="disabled"
            :aria-invalid="error ? true : undefined"
            :aria-describedby="describedBy"
            @update:model-value="search"
        />
        <p v-if="selected" class="text-sm text-xq-foreground">{{ selected.label }}</p>
        <p v-if="failed" class="text-xs text-xq-destructive">The search failed.</p>
        <ul v-if="results.length" class="gap-1 grid">
            <li v-for="option in results" :key="option.value">
                <button
                    type="button"
                    class="text-sm text-xq-primary"
                    @click="value = String(option.value)"
                >
                    {{ option.label }}
                </button>
            </li>
        </ul>
    </XqFieldWrapper>
</template>
