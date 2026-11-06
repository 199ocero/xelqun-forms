<script setup lang="ts">
import { computed, ref } from 'vue';
import { gridStyle } from '../../composables/layout';
import { useField } from '../../composables/useField';
import type { SchemaNode, SelectOption } from '../../schema/types';
import XqButton from '../../ui/XqButton.vue';
import XqCheckbox from '../../ui/XqCheckbox.vue';
import XqInput from '../../ui/XqInput.vue';
import XqLabel from '../../ui/XqLabel.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';
import { useTranslator } from '../../composables/context';

const t = useTranslator();
type Value = string | number;

const props = defineProps<{ node: SchemaNode }>();
const { value, error, label, describedBy, required, disabled, id } = useField<Value[]>(props.node);
const options = computed<SelectOption[]>(() => props.node.props?.options ?? []);
const search = ref('');
const shown = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return needle
        ? options.value.filter((option) => option.label.toLowerCase().includes(needle))
        : options.value;
});
const selected = computed(() => new Set((value.value ?? []).map(String)));

function isChecked(option: SelectOption): boolean {
    return selected.value.has(String(option.value));
}

function toggle(option: SelectOption, checked: boolean): void {
    const others = (value.value ?? []).filter((item) => String(item) !== String(option.value));

    value.value = checked ? [...others, option.value] : others;
}

function setAll(checked: boolean): void {
    const enabled = options.value.filter((option) => !option.disabled);
    const others = (value.value ?? []).filter(
        (item) => !enabled.some((option) => String(option.value) === String(item)),
    );

    value.value = checked ? [...others, ...enabled.map((option) => option.value)] : others;
}
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :label-for="null" :error="error" :required="required">
        <div class="gap-3 grid">
            <div v-if="node.props?.searchable || node.props?.bulkToggleable" class="gap-2 flex">
                <XqInput
                    v-if="node.props?.searchable"
                    v-model="search"
                    type="search"
                    placeholder="Search…"
                    :aria-label="t('Search :label', { label: label ?? '' })"
                />
                <template v-if="node.props?.bulkToggleable">
                    <XqButton
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="disabled"
                        @click="setAll(true)"
                    >
                        {{ t('Select all') }}
                    </XqButton>
                    <XqButton
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="disabled"
                        @click="setAll(false)"
                    >
                        {{ t('Deselect all') }}
                    </XqButton>
                </template>
            </div>
            <div
                role="group"
                class="xq-grid gap-2"
                :style="gridStyle(node.props?.columns)"
                :aria-labelledby="label ? `${id}-label` : undefined"
                :aria-describedby="describedBy"
            >
                <div
                    v-for="(option, index) in shown"
                    :key="String(option.value)"
                    class="gap-2.5 flex items-start"
                >
                    <XqCheckbox
                        :id="`${id}-${index}`"
                        :model-value="isChecked(option)"
                        :disabled="disabled || option.disabled"
                        class="mt-0.5"
                        @update:model-value="toggle(option, !!$event)"
                    />
                    <div class="gap-1 grid">
                        <XqLabel
                            :for="`${id}-${index}`"
                            class="leading-snug font-normal cursor-pointer"
                            >{{ option.label }}</XqLabel
                        >
                        <p v-if="option.description" class="text-xs text-xq-muted-foreground">
                            {{ option.description }}
                        </p>
                    </div>
                </div>
                <p v-if="shown.length === 0" class="text-sm text-xq-muted-foreground">No results</p>
            </div>
        </div>
    </XqFieldWrapper>
</template>
