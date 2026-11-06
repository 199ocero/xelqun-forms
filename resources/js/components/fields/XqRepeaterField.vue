<script setup lang="ts">
import { GripVertical, Plus } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { gridStyle } from '../../composables/layout';
import { useField } from '../../composables/useField';
import { useSortable } from '../../composables/useSortable';
import { startsWithPath } from '../../core/paths';
import { cn, focusFirstEnabled } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import XqButton from '../../ui/XqButton.vue';
import XqConfirmDialog from '../../ui/XqConfirmDialog.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';
import XqNode from '../XqNode.vue';
import XqRepeaterRowActions from './XqRepeaterRowActions.vue';
import { useTranslator } from '../../composables/context';

const t = useTranslator();
const props = defineProps<{ node: SchemaNode }>();
const { store, path, error, required, disabled, id } = useField(props.node);
const options = computed(() => props.node.props ?? {});
const keys = computed(() => store.rowKeys(props.node));
const rows = computed<Record<string, SchemaNode[]>>(() => options.value.rows ?? {});
const container = ref<HTMLElement | null>(null);
const rowList = ref<HTMLElement | null>(null);
const isTable = computed(() => !!options.value.table);
const isSimple = computed(() => !!options.value.simple);

const canAdd = computed(
    () =>
        !disabled.value &&
        options.value.addable !== false &&
        (options.value.maxItems === null ||
            options.value.maxItems === undefined ||
            keys.value.length < options.value.maxItems),
);
const canRemove = computed(
    () => !disabled.value && keys.value.length > (options.value.minItems ?? 0),
);

/** The attributes the action buttons need, the same in every layout. */
function actions(index: number) {
    return {
        label: options.value.itemLabel,
        index,
        count: keys.value.length,
        disabled: disabled.value,
        orderable: options.value.orderable,
        showClone: !!options.value.cloneable,
        canClone: canAdd.value,
        showRemove: options.value.deletable !== false,
        canRemove: canRemove.value,
    };
}

function titleOf(index: number): string {
    return options.value.itemNumbers === false
        ? options.value.itemLabel
        : `${options.value.itemLabel} ${index + 1}`;
}

function rowSelector(key: string): string {
    return `[data-xq-row="${CSS.escape(key)}"]`;
}

async function focusRow(key: string): Promise<void> {
    await nextTick();
    store.root.value
        ?.querySelector<HTMLElement>(
            `${rowSelector(key)} input:not([type=hidden]), ${rowSelector(key)} button[role=combobox]`,
        )
        ?.focus();
}

async function remove(key: string, index: number): Promise<void> {
    store.removeRow(props.node, key);

    await nextTick();

    const next = keys.value[Math.min(index, keys.value.length - 1)];

    focusFirstEnabled(container.value, [
        ...(next ? [`${rowSelector(next)} [data-xq-remove]`] : []),
        '[data-xq-add]',
    ]);
}

/** Kept when the dialog closes, because closing runs before the confirm click. */
const pendingRemoval = ref<{ key: string; index: number } | null>(null);
const isConfirming = ref(false);

function requestRemove(key: string, index: number): void {
    if (options.value.deleteConfirmation) {
        pendingRemoval.value = { key, index };
        isConfirming.value = true;
    } else {
        void remove(key, index);
    }
}

function confirmRemove(): void {
    if (pendingRemoval.value) {
        void remove(pendingRemoval.value.key, pendingRemoval.value.index);
    }
}

async function move(key: string, direction: -1 | 1): Promise<void> {
    store.moveRow(props.node, key, direction);

    await nextTick();

    const row = rowSelector(key);

    focusFirstEnabled(container.value, [
        `${row} [data-xq-move="${direction}"]`,
        `${row} [data-xq-move="${-direction}"]`,
    ]);
}

function add(): void {
    void focusRow(store.addRow(props.node));
}

function clone(key: string): void {
    void focusRow(store.cloneRow(props.node, key));
}

const collapsedRows = ref(new Set<string>(options.value.collapsed ? keys.value : []));

function hasError(key: string): boolean {
    return Object.keys(store.errors).some((errorPath) =>
        startsWithPath(errorPath, `${path}.${key}`),
    );
}

// Opened for good: if this only lasted as long as the error, typing the fix would close the row.
watch(
    () => keys.value.filter(hasError),
    (rowsWithErrors) => rowsWithErrors.forEach((key) => collapsedRows.value.delete(key)),
);

function isCollapsed(key: string): boolean {
    return collapsedRows.value.has(key);
}

function toggle(key: string): void {
    if (!collapsedRows.value.delete(key)) {
        collapsedRows.value.add(key);
    }
}

const canDrag = computed(
    () => !!options.value.draggable && !disabled.value && keys.value.length > 1,
);

useSortable(rowList, {
    draggable: '> [data-xq-row]',
    handle: `[data-xq-drag="${path}"]`,
    enabled: () => canDrag.value,
    onMove: (from, to) => store.moveRow(props.node, keys.value[from], to - from),
});
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required">
        <div ref="container" class="gap-3 grid">
            <div v-if="isTable" class="overflow-x-auto rounded-xq-lg border border-xq-border">
                <table class="text-sm w-full border-collapse">
                    <thead>
                        <tr class="border-b border-xq-border bg-xq-muted/50">
                            <th
                                v-for="column in options.table"
                                :key="column.label"
                                scope="col"
                                :style="column.width ? { width: column.width } : undefined"
                                class="px-3 py-2 font-medium text-left text-xq-muted-foreground"
                            >
                                <span :class="column.hiddenLabel ? 'sr-only' : undefined">
                                    {{ column.label }}
                                </span>
                                <span
                                    v-if="column.required"
                                    class="text-xq-destructive"
                                    aria-hidden="true"
                                    >*</span
                                >
                            </th>
                            <th scope="col" class="w-px">
                                <span class="sr-only">{{ t('Actions') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody ref="rowList">
                        <tr
                            v-for="(key, index) in keys"
                            :key="key"
                            :data-xq-row="key"
                            :class="cn('border-b border-xq-border last:border-b-0')"
                        >
                            <td
                                v-for="child in rows[key] ?? []"
                                :key="child.key"
                                class="px-3 py-2 align-top [&_[data-slot=xq-label]]:sr-only"
                            >
                                <XqNode :node="child" />
                            </td>
                            <td class="px-2 py-2 align-top">
                                <div class="gap-1 flex items-center justify-end">
                                    <span
                                        v-if="canDrag"
                                        aria-hidden="true"
                                        class="cursor-grab touch-none text-xq-muted-foreground"
                                        :data-xq-drag="path"
                                    >
                                        <GripVertical class="size-4" />
                                    </span>
                                    <XqRepeaterRowActions
                                        v-bind="actions(index)"
                                        @move="move(key, $event)"
                                        @clone="clone(key)"
                                        @remove="requestRemove(key, index)"
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else ref="rowList" class="xq-grid gap-3" :style="gridStyle(options.grid)">
                <div
                    v-for="(key, index) in keys"
                    :key="key"
                    :data-xq-row="key"
                    role="group"
                    :aria-label="`${options.itemLabel} ${index + 1}`"
                    :class="
                        cn(
                            !isSimple &&
                                'shadow-xs rounded-xq-lg border border-xq-border bg-xq-card',
                            isSimple && 'gap-2 flex items-start',
                        )
                    "
                >
                    <template v-if="isSimple">
                        <span
                            v-if="canDrag"
                            aria-hidden="true"
                            class="mt-2.5 cursor-grab touch-none text-xq-muted-foreground"
                            :data-xq-drag="path"
                        >
                            <GripVertical class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <XqNode
                                v-for="child in rows[key] ?? []"
                                :key="child.key"
                                :node="child"
                            />
                        </div>
                        <XqRepeaterRowActions
                            v-bind="actions(index)"
                            class="mt-1"
                            @move="move(key, $event)"
                            @clone="clone(key)"
                            @remove="requestRemove(key, index)"
                        />
                    </template>
                    <template v-else>
                        <div
                            :class="
                                cn(
                                    'gap-2 px-3 py-1.5 flex items-center justify-between',
                                    !isCollapsed(key) && 'border-b border-xq-border',
                                )
                            "
                        >
                            <div class="gap-1.5 min-w-0 flex items-center">
                                <span
                                    v-if="canDrag"
                                    aria-hidden="true"
                                    class="cursor-grab touch-none text-xq-muted-foreground"
                                    :data-xq-drag="path"
                                >
                                    <GripVertical class="size-4" />
                                </span>
                                <span class="text-sm font-medium truncate text-xq-muted-foreground">
                                    {{ titleOf(index) }}
                                </span>
                            </div>
                            <XqRepeaterRowActions
                                v-bind="actions(index)"
                                :collapsible="options.collapsible"
                                :collapsed="isCollapsed(key)"
                                @move="move(key, $event)"
                                @clone="clone(key)"
                                @remove="requestRemove(key, index)"
                                @toggle="toggle(key)"
                            />
                        </div>
                        <div
                            v-show="!isCollapsed(key)"
                            class="xq-grid p-3"
                            :style="gridStyle(options.columns)"
                        >
                            <XqNode
                                v-for="child in rows[key] ?? []"
                                :key="child.key"
                                :node="child"
                            />
                        </div>
                    </template>
                </div>
            </div>
            <div
                v-if="keys.length === 0"
                class="px-4 py-6 text-sm rounded-xq-lg border border-dashed border-xq-border text-center text-xq-muted-foreground"
            >
                No {{ String(options.itemLabel ?? 'item').toLowerCase() }}s yet.
            </div>
            <div v-if="options.addable !== false">
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
        <XqConfirmDialog
            v-model:open="isConfirming"
            :title="`Remove ${String(options.itemLabel).toLowerCase()}`"
            :message="options.deleteConfirmation ?? ''"
            @confirm="confirmRemove"
        />
    </XqFieldWrapper>
</template>
