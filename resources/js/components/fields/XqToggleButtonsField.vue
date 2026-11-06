<script setup lang="ts">
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';
import { computed } from 'vue';
import { gridStyle } from '../../composables/layout';
import { useField } from '../../composables/useField';
import { cn } from '../../lib/utils';
import type { SchemaNode, SelectOption } from '../../schema/types';
import XqIcon from '../../ui/XqIcon.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';

type Value = string | number;
type ToggleOption = SelectOption & { color?: string | null; icon?: string | null };

const PRESSED: Record<string, string> = {
    primary: 'data-[state=on]:bg-xq-primary data-[state=on]:text-xq-primary-foreground',
    success: 'data-[state=on]:bg-xq-success data-[state=on]:text-white',
    danger: 'data-[state=on]:bg-xq-destructive data-[state=on]:text-xq-destructive-foreground',
    warning: 'data-[state=on]:bg-amber-500 data-[state=on]:text-black',
    info: 'data-[state=on]:bg-sky-600 data-[state=on]:text-white',
};

const props = defineProps<{ node: SchemaNode }>();
const { value, error, label, describedBy, required, disabled, id } = useField<
    Value | Value[] | null
>(props.node);
const options = computed<ToggleOption[]>(() => props.node.props?.options ?? []);
const isMultiple = computed(() => !!props.node.props?.multiple);
const isGrouped = computed(() => !!props.node.props?.grouped);
const isInline = computed(() => !!props.node.props?.inline);
const isGrid = computed(() => !isGrouped.value && !isInline.value);

const model = computed<any>({
    get: () => value.value ?? undefined,
    set: (next) => (value.value = next ?? (isMultiple.value ? [] : null)),
});
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :label-for="null" :error="error" :required="required">
        <ToggleGroupRoot
            v-model="model"
            :type="isMultiple ? 'multiple' : 'single'"
            :disabled="disabled"
            :aria-labelledby="label ? `${id}-label` : undefined"
            :aria-describedby="describedBy"
            :style="isGrid ? gridStyle(node.props?.columns) : undefined"
            :class="
                cn(
                    isGrid && 'xq-grid gap-2',
                    isInline && 'gap-2 flex flex-wrap',
                    isGrouped && 'shadow-xs inline-flex w-fit rounded-xq-md',
                )
            "
        >
            <ToggleGroupItem
                v-for="option in options"
                :key="String(option.value)"
                :value="option.value"
                :disabled="option.disabled"
                :class="
                    cn(
                        'gap-2 h-9 px-3 text-sm font-medium inline-flex cursor-pointer items-center justify-center border border-xq-input bg-xq-background text-xq-foreground transition-colors outline-none hover:bg-xq-accent focus-visible:ring-[3px] focus-visible:ring-xq-ring/25 disabled:cursor-not-allowed disabled:opacity-50',
                        isGrouped
                            ? 'first:ml-0 -ml-px first:rounded-l-xq-md last:rounded-r-xq-md'
                            : 'rounded-xq-md',
                        PRESSED[option.color ?? 'primary'],
                    )
                "
            >
                <XqIcon v-if="option.icon" :name="option.icon" />
                {{ option.label }}
            </ToggleGroupItem>
        </ToggleGroupRoot>
    </XqFieldWrapper>
</template>
