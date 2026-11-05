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
const selected = computed<SelectOption | undefined>(() =>
    [...(props.node.props?.options ?? []), ...results.value].find(
        (option) => String(option.value) === String(value.value),
    ),
);

async function search(term: string): Promise<void> {
    failed.value = false;

    try {
        results.value = term === '' ? [] : await store.search(path, term);
    } catch {
        failed.value = true;
    }
}

function pick(option: SelectOption): void {
    value.value = String(option.value);
    results.value = [option];
}
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required">
        <XqInput
            :id="id"
            placeholder="Search people"
            :disabled="disabled"
            :aria-invalid="error ? true : undefined"
            :aria-describedby="describedBy"
            @input="search(($event.target as HTMLInputElement).value)"
        />
        <p v-if="failed" class="text-xs text-xq-destructive">The search failed.</p>
        <p v-if="selected" class="text-sm text-xq-foreground" data-reviewer-selected>
            {{ selected.label }}
        </p>
        <ul v-if="results.length" class="grid gap-1">
            <li v-for="option in results" :key="option.value">
                <button type="button" class="text-sm text-xq-primary" @click="pick(option)">
                    {{ option.label }}
                </button>
            </li>
        </ul>
    </XqFieldWrapper>
</template>
