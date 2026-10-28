<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-vue-next';
import { computed, nextTick } from 'vue';
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
        <div class="gap-3 grid">
            <div
                v-for="(key, index) in keys"
                :key="key"
                :data-xq-row="key"
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
                                @click="store.moveRow(node, key, -1)"
                            >
                                <ArrowUp />
                            </XqButton>
                            <XqButton
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :disabled="disabled || index === keys.length - 1"
                                :aria-label="`Move ${options.itemLabel} ${index + 1} down`"
                                @click="store.moveRow(node, key, 1)"
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
                            @click="store.removeRow(node, key)"
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
                    @click="add"
                >
                    <Plus />
                    {{ options.addLabel }}
                </XqButton>
            </div>
        </div>
    </XqFieldWrapper>
</template>
