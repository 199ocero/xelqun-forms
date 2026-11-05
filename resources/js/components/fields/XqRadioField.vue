<script setup lang="ts">
import { useField } from '../../composables/useField';
import { cn } from '../../lib/utils';
import type { SchemaNode, SelectOption } from '../../schema/types';
import XqLabel from '../../ui/XqLabel.vue';
import XqRadioGroup from '../../ui/XqRadioGroup.vue';
import XqRadioGroupItem from '../../ui/XqRadioGroupItem.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';

const props = defineProps<{ node: SchemaNode }>();
const { value, error, label, describedBy, required, disabled, id } = useField<
    string | number | null
>(props.node);
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :label-for="null" :error="error" :required="required">
        <XqRadioGroup
            v-model="value"
            :disabled="disabled"
            :orientation="node.props?.inline ? 'horizontal' : 'vertical'"
            :aria-invalid="error ? true : undefined"
            :aria-required="required || undefined"
            :aria-describedby="describedBy"
            :aria-labelledby="label ? `${id}-label` : undefined"
            :class="cn(node.props?.inline && 'gap-x-6 gap-y-2 flex flex-wrap')"
        >
            <div
                v-for="(option, index) in (node.props?.options ?? []) as SelectOption[]"
                :key="String(option.value)"
                class="gap-2.5 flex items-start"
            >
                <XqRadioGroupItem :id="`${id}-${index}`" :value="option.value" />
                <div class="gap-1 grid">
                    <XqLabel :for="`${id}-${index}`" class="leading-4 font-normal cursor-pointer">{{
                        option.label
                    }}</XqLabel>
                    <p v-if="option.description" class="text-xs text-xq-muted-foreground">
                        {{ option.description }}
                    </p>
                </div>
            </div>
        </XqRadioGroup>
    </XqFieldWrapper>
</template>
