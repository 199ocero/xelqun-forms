<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import { gridStyle } from '../../composables/layout';
import { useField } from '../../composables/useField';
import type { SchemaNode } from '../../schema/types';
import XqButton from '../../ui/XqButton.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';
import XqNode from '../XqNode.vue';

const props = defineProps<{ node: SchemaNode }>();
const { store, error, required, disabled, id } = useField(props.node);
const options = computed(() => props.node.props ?? {});
const keys = computed(() => store.rowKeys(props.node));
const rows = computed<Record<string, SchemaNode[]>>(() => options.value.rows ?? {});
const container = ref<HTMLElement | null>(null);
const canAdd = computed(
    () =>
        !disabled.value &&
        (options.value.maxItems === null ||
            options.value.maxItems === undefined ||
            keys.value.length < options.value.maxItems),
);
const canRemove = computed(
    () => !disabled.value && keys.value.length > (options.value.minItems ?? 0),
);

/**
 * Keep keyboard users in place: focus the first of these that can take it.
 */
function focusFirstEnabled(selectors: string[]): void {
    for (const selector of selectors) {
        const button = container.value?.querySelector<HTMLButtonElement>(selector);

        if (button && !button.disabled) {
            button.focus();

            return;
        }
    }
}

function rowSelector(key: string): string {
    return `[data-xq-row="${CSS.escape(key)}"]`;
}

async function remove(key: string, index: number): Promise<void> {
    store.removeRow(props.node, key);

    await nextTick();

    const next = keys.value[Math.min(index, keys.value.length - 1)];

    focusFirstEnabled([
        ...(next ? [`${rowSelector(next)} [data-xq-remove]`] : []),
        '[data-xq-add]',
    ]);
}

async function move(key: string, direction: -1 | 1): Promise<void> {
    store.moveRow(props.node, key, direction);

    await nextTick();

    const row = rowSelector(key);

    focusFirstEnabled([
        `${row} [data-xq-move="${direction}"]`,
        `${row} [data-xq-move="${-direction}"]`,
    ]);
}

async function add(): Promise<void> {
    const key = store.addRow(props.node);

    await nextTick();
    store.root.value
        ?.querySelector<HTMLElement>(
            `[data-xq-row="${CSS.escape(key)}"] input:not([type=hidden]), [data-xq-row="${CSS.escape(key)}"] button[role=combobox]`,
        )
        ?.focus();
}
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required">
        <div ref="container" class="gap-3 grid">
            <div
                v-for="(key, index) in keys"
                :key="key"
                :data-xq-row="key"
                role="group"
                :aria-label="`${options.itemLabel} ${index + 1}`"
                class="shadow-xs rounded-xq-lg border border-xq-border bg-xq-card"
            >
                <div
                    class="gap-2 px-3 py-1.5 flex items-center justify-between border-b border-xq-border"
                >
                    <span class="text-sm font-medium text-xq-muted-foreground"
                        >{{ options.itemLabel }} {{ index + 1 }}</span
                    >
                    <div class="gap-0.5 flex items-center">
                        <template v-if="options.orderable && keys.length > 1">
                            <XqButton
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :disabled="disabled || index === 0"
                                :aria-label="`Move ${options.itemLabel} ${index + 1} up`"
                                data-xq-move="-1"
                                @click="move(key, -1)"
                            >
                                <ArrowUp />
                            </XqButton>
                            <XqButton
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :disabled="disabled || index === keys.length - 1"
                                :aria-label="`Move ${options.itemLabel} ${index + 1} down`"
                                data-xq-move="1"
                                @click="move(key, 1)"
                            >
                                <ArrowDown />
                            </XqButton>
                        </template>
                        <XqButton
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="text-xq-muted-foreground hover:text-xq-destructive"
                            :disabled="!canRemove"
                            :aria-label="`Remove ${options.itemLabel} ${index + 1}`"
                            data-xq-remove
                            @click="remove(key, index)"
                        >
                            <Trash2 />
                        </XqButton>
                    </div>
                </div>
                <div class="xq-grid p-3" :style="gridStyle(options.columns)">
                    <XqNode v-for="child in rows[key] ?? []" :key="child.key" :node="child" />
                </div>
            </div>
            <div
                v-if="keys.length === 0"
                class="px-4 py-6 text-sm rounded-xq-lg border border-dashed border-xq-border text-center text-xq-muted-foreground"
            >
                No {{ String(options.itemLabel ?? 'item').toLowerCase() }}s yet.
            </div>
            <div>
                <XqButton
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="!canAdd"
                    data-xq-add
                    @click="add"
                >
                    <Plus />
                    {{ options.addLabel }}
                </XqButton>
            </div>
        </div>
    </XqFieldWrapper>
</template>
