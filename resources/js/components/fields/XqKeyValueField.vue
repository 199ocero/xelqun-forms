<script setup lang="ts">
import { ChevronDown, ChevronUp, GripVertical, Plus, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useField } from '../../composables/useField';
import { useSortable } from '../../composables/useSortable';
import type { SchemaNode } from '../../schema/types';
import { moveItem } from '../../ui/options';
import XqButton from '../../ui/XqButton.vue';
import XqInput from '../../ui/XqInput.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';
import { useTranslator } from '../../composables/context';

const t = useTranslator();
interface Row {
    id: number;
    key: string;
    value: string;
}

const props = defineProps<{ node: SchemaNode }>();
const { value, error, describedBy, required, disabled, id } = useField<Record<string, unknown>>(
    props.node,
);
const options = computed(() => props.node.props ?? {});
let nextRowId = 0;

function toRows(state: unknown): Row[] {
    const map = state && !Array.isArray(state) ? (state as Record<string, unknown>) : {};

    return Object.entries(map).map(([key, item]) => ({
        id: nextRowId++,
        key,
        value: item === null || item === undefined ? '' : String(item),
    }));
}

function toMap(list: Row[]): Record<string, string> {
    return Object.fromEntries(
        list.filter((row) => row.key.trim() !== '').map((row) => [row.key, row.value]),
    );
}

/** Rows keep a blank key and a stable id while typing, which the map can't hold. */
const rows = ref<Row[]>(toRows(value.value));

function commit(): void {
    value.value = toMap(rows.value);
}

watch(value, (next) => {
    if (JSON.stringify(next ?? {}) !== JSON.stringify(toMap(rows.value))) {
        rows.value = toRows(next);
    }
});

function add(): void {
    rows.value.push({ id: nextRowId++, key: '', value: '' });
}

function remove(row: Row): void {
    rows.value = rows.value.filter((item) => item.id !== row.id);
    commit();
}

function move(row: Row, offset: number): void {
    rows.value = moveItem(rows.value, row, offset);
    commit();
}

const rowList = ref<HTMLElement | null>(null);

useSortable(rowList, {
    draggable: '> [data-xq-row]',
    handle: '[data-xq-drag]',
    enabled: () => !!options.value.reorderable && !disabled.value && rows.value.length > 1,
    onMove: (from, to) => move(rows.value[from], to - from),
});
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :label-for="null" :error="error" :required="required">
        <div class="gap-2 grid">
            <div
                v-if="rows.length"
                class="gap-2 text-xs font-medium grid grid-cols-2 text-xq-muted-foreground"
            >
                <span>{{ options.keyLabel }}</span>
                <span>{{ options.valueLabel }}</span>
            </div>
            <div ref="rowList" class="gap-2 grid">
                <div
                    v-for="(row, index) in rows"
                    :key="row.id"
                    data-xq-row
                    class="gap-2 flex items-center"
                >
                    <span
                        v-if="options.reorderable && rows.length > 1"
                        aria-hidden="true"
                        data-xq-drag
                        class="cursor-grab touch-none text-xq-muted-foreground"
                    >
                        <GripVertical class="size-4" />
                    </span>
                    <XqInput
                        v-model="row.key"
                        :aria-label="`${options.keyLabel} ${index + 1}`"
                        :aria-describedby="describedBy"
                        :placeholder="options.keyPlaceholder ?? undefined"
                        :disabled="disabled || !options.editableKeys"
                        @update:model-value="commit"
                    />
                    <XqInput
                        v-model="row.value"
                        :aria-label="`${options.valueLabel} ${index + 1}`"
                        :placeholder="options.valuePlaceholder ?? undefined"
                        :disabled="disabled || !options.editableValues"
                        @update:model-value="commit"
                    />
                    <template v-if="options.reorderable">
                        <XqButton
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="t('Move row :number up', { number: index + 1 })"
                            :disabled="disabled || index === 0"
                            @click="move(row, -1)"
                        >
                            <ChevronUp />
                        </XqButton>
                        <XqButton
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="t('Move row :number down', { number: index + 1 })"
                            :disabled="disabled || index === rows.length - 1"
                            @click="move(row, 1)"
                        >
                            <ChevronDown />
                        </XqButton>
                    </template>
                    <XqButton
                        v-if="options.deletable"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="t('Remove row :number', { number: index + 1 })"
                        :disabled="disabled"
                        @click="remove(row)"
                    >
                        <X />
                    </XqButton>
                </div>
            </div>
            <XqButton
                v-if="options.addable"
                type="button"
                variant="outline"
                size="sm"
                class="justify-self-start"
                :disabled="disabled"
                @click="add"
            >
                <Plus />{{ options.addLabel }}
            </XqButton>
        </div>
    </XqFieldWrapper>
</template>
