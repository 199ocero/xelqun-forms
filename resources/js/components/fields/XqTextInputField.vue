<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useField } from '../../composables/useField';
import { isNumeric, toNumber } from '../../core/values';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { controlClass } from '../../ui/classes';
import XqFieldWrapper from '../XqFieldWrapper.vue';

const props = defineProps<{ node: SchemaNode }>();
const { path, value, error, describedBy, required, disabled, id, onBlur } = useField(props.node);
const options = computed(() => props.node.props ?? {});

function format(next: unknown): string {
    return next === null || next === undefined ? '' : String(next);
}

/**
 * Keep the typed text locally so "1." or "-" can be typed into numeric fields
 * while the state already holds a number.
 */
const text = ref(format(value.value));

watch(value, (next) => {
    const matchesText = options.value.numeric
        ? text.value.trim() !== '' && isNumeric(text.value) && toNumber(text.value) === next
        : text.value === next;

    if (!matchesText) {
        text.value = format(next);
    }
});

function onInput(event: Event): void {
    text.value = (event.target as HTMLInputElement).value;

    if (!options.value.numeric) {
        value.value = text.value;

        return;
    }

    const trimmed = text.value.trim();
    value.value = trimmed === '' ? null : isNumeric(trimmed) ? toNumber(trimmed) : trimmed;
}

const inputType = computed(() =>
    options.value.numeric ? 'text' : (options.value.inputType ?? 'text'),
);
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required">
        <div
            :class="
                cn(
                    controlClass,
                    'h-9 flex items-center overflow-hidden focus-within:border-xq-ring focus-within:ring-[3px] focus-within:ring-xq-ring/25',
                    error && 'border-xq-destructive',
                    disabled && 'bg-xq-muted/60',
                )
            "
        >
            <span
                v-if="options.prefix"
                class="px-3 text-sm flex h-full items-center border-r border-xq-input bg-xq-muted text-xq-muted-foreground"
            >
                {{ options.prefix }}
            </span>
            <input
                :id="id"
                :name="path"
                :value="text"
                :type="inputType"
                :inputmode="options.numeric ? 'decimal' : undefined"
                :placeholder="node.placeholder ?? undefined"
                :disabled="disabled"
                :required="required"
                :maxlength="options.maxLength ?? undefined"
                :autocomplete="options.autocomplete ?? undefined"
                :aria-invalid="error ? true : undefined"
                :aria-describedby="describedBy"
                :class="
                    cn(
                        'min-w-0 px-3 h-full flex-1 bg-transparent outline-none placeholder:text-xq-muted-foreground disabled:cursor-not-allowed',
                        options.numeric && 'tabular-nums',
                    )
                "
                @input="onInput"
                @blur="onBlur"
            />
            <span
                v-if="options.suffix"
                class="px-3 text-sm flex h-full items-center border-l border-xq-input bg-xq-muted text-xq-muted-foreground"
            >
                {{ options.suffix }}
            </span>
        </div>
    </XqFieldWrapper>
</template>
