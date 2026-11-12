<script setup lang="ts">
import { Check, Copy, Eye, EyeOff, LoaderCircle } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useField } from '../../composables/useField';
import { applyMask } from '../../core/mask';
import { isNumeric, toNumber } from '../../core/values';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { controlClass, spinnerClass } from '../../ui/classes';
import XqIcon from '../../ui/XqIcon.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';
import { useTranslator } from '../../composables/context';

const t = useTranslator();
const props = defineProps<{ node: SchemaNode }>();
const {
    path,
    value,
    error,
    placeholder,
    describedBy,
    showLoading,
    required,
    disabled,
    readOnly,
    id,
    onBlur,
} = useField(props.node);
const options = computed(() => props.node.props ?? {});
const inputDisabled = computed(() => disabled.value && !readOnly.value);

function format(next: unknown): string {
    const value = next === null || next === undefined ? '' : String(next);

    return options.value.mask ? applyMask(value, options.value.mask) : value;
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
    const input = event.target as HTMLInputElement;

    if (options.value.mask) {
        const typed = input.value;
        const caret = input.selectionStart ?? typed.length;

        input.value = applyMask(typed, options.value.mask);

        const position = Math.max(0, caret + input.value.length - typed.length);

        input.setSelectionRange?.(position, position);
    }

    text.value = input.value;

    if (!options.value.numeric) {
        value.value = text.value;

        return;
    }

    const trimmed = text.value.trim();
    value.value = trimmed === '' ? null : isNumeric(trimmed) ? toNumber(trimmed) : trimmed;
}

const revealed = ref(false);
const inputType = computed(() => {
    if (options.value.numeric || (revealed.value && options.value.revealable)) {
        return 'text';
    }

    return options.value.inputType ?? 'text';
});

const copied = ref(false);
let copiedTimer: ReturnType<typeof setTimeout> | null = null;

async function copy(): Promise<void> {
    if (!navigator.clipboard) {
        return;
    }

    try {
        await navigator.clipboard.writeText(text.value);
    } catch {
        return;
    }

    copied.value = true;

    if (copiedTimer) {
        clearTimeout(copiedTimer);
    }

    copiedTimer = setTimeout(() => (copied.value = false), options.value.copyable?.duration);
}

onBeforeUnmount(() => {
    if (copiedTimer) {
        clearTimeout(copiedTimer);
    }
});
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required" control-shows-loading>
        <div
            :class="
                cn(
                    controlClass,
                    'h-10 flex items-center overflow-hidden focus-within:border-xq-ring focus-within:ring-[3px] focus-within:ring-xq-ring/25',
                    error && 'border-xq-destructive',
                    inputDisabled && 'bg-xq-muted/60',
                )
            "
        >
            <span
                v-if="options.prefix"
                class="px-3 text-sm flex h-full items-center border-r border-xq-input bg-xq-muted text-xq-muted-foreground"
            >
                {{ options.prefix }}
            </span>
            <XqIcon
                v-if="options.prefixIcon"
                :name="options.prefixIcon"
                class="ml-3 text-xq-muted-foreground"
            />
            <!-- First, so the field's own attributes win over extra ones. -->
            <input
                v-bind="node.extraInputAttributes"
                :id="id"
                :name="path"
                :value="text"
                :type="inputType"
                :inputmode="options.numeric ? (options.integer ? 'numeric' : 'decimal') : undefined"
                :placeholder="placeholder ?? undefined"
                :disabled="inputDisabled"
                :readonly="readOnly"
                :required="required"
                :maxlength="options.maxLength ?? undefined"
                :autocomplete="options.autocomplete ?? undefined"
                :list="options.datalist ? `${id}-list` : undefined"
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
            <datalist v-if="options.datalist" :id="`${id}-list`">
                <option
                    v-for="suggestion in options.datalist"
                    :key="suggestion"
                    :value="suggestion"
                />
            </datalist>
            <LoaderCircle v-if="showLoading" :class="cn(spinnerClass, 'mr-3')" aria-hidden="true" />
            <XqIcon
                v-if="options.suffixIcon"
                :name="options.suffixIcon"
                class="mr-3 text-xq-muted-foreground"
            />
            <button
                v-if="options.revealable"
                type="button"
                class="mr-2 size-6 rounded-sm flex cursor-pointer items-center justify-center text-xq-muted-foreground hover:text-xq-foreground"
                :aria-label="revealed ? t('Hide password') : t('Show password')"
                :aria-pressed="revealed"
                @click="revealed = !revealed"
            >
                <component :is="revealed ? EyeOff : Eye" class="size-4" />
            </button>
            <button
                v-if="options.copyable"
                type="button"
                class="mr-2 size-6 rounded-sm flex cursor-pointer items-center justify-center text-xq-muted-foreground hover:text-xq-foreground"
                :aria-label="t('Copy')"
                @click="copy"
            >
                <component :is="copied ? Check : Copy" class="size-4" />
            </button>
            <span v-if="options.copyable" role="status" class="sr-only">{{
                copied ? options.copyable.message : ''
            }}</span>
            <span
                v-if="options.suffix"
                class="px-3 text-sm flex h-full items-center border-l border-xq-input bg-xq-muted text-xq-muted-foreground"
            >
                {{ options.suffix }}
            </span>
        </div>
    </XqFieldWrapper>
</template>
