<script setup lang="ts">
import { getLocalTimeZone, parseDate } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import { CalendarDays, LoaderCircle, X } from '@lucide/vue';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';
import { computed, ref } from 'vue';
import { useField } from '../../composables/useField';
import { formatPhpDate } from '../../core/dates';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { controlClass, popoverClass, spinnerClass } from '../../ui/classes';
import XqCalendar from '../../ui/XqCalendar.vue';
import XqTimeField from '../../ui/XqTimeField.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';
import { useTranslator } from '../../composables/context';

const t = useTranslator();
const props = defineProps<{ node: SchemaNode }>();
const {
    store,
    value,
    error,
    label: fieldLabel,
    placeholder,
    describedBy,
    showLoading,
    required,
    disabled,
    id,
    onBlur,
} = useField<string | null>(props.node);
const open = ref(false);
const options = computed(() => props.node.props ?? {});
const hasTime = computed(() => !!options.value.time);
const hasDate = computed(() => !options.value.timeOnly);

/** A time picked before its date has nowhere to go yet, so it waits here. */
const pendingTime = ref<string | null>(null);

const timeText = computed<string | null>({
    get() {
        if (!value.value) {
            return pendingTime.value;
        }

        return hasDate.value ? value.value.slice(11) || null : value.value;
    },
    set(next) {
        if (!hasDate.value) {
            value.value = next;
        } else if (value.value) {
            // A half-typed time comes through as null, and the value should wait for the rest.
            if (next) {
                value.value = `${value.value.slice(0, 10)} ${next}`;
            }
        } else {
            pendingTime.value = next;
        }

        onBlur();
    },
});

function midnight(): string {
    return options.value.seconds ? '00:00:00' : '00:00';
}

function parse(input: unknown): DateValue | undefined {
    if (typeof input !== 'string' || input === '') {
        return undefined;
    }

    try {
        return parseDate(input.slice(0, 10));
    } catch {
        return undefined;
    }
}

const date = computed<DateValue | undefined>({
    get: () => parse(value.value),
    set: (next) => {
        if (next) {
            value.value = hasTime.value
                ? `${next.toString()} ${timeText.value ?? midnight()}`
                : next.toString();
        } else {
            value.value = null;
        }

        open.value = false;
        onBlur();
    },
});

const disabledDates = computed(() => new Set<string>(options.value.disabledDates ?? []));

const label = computed(() => {
    if (!date.value) {
        return null;
    }

    const shown = date.value.toDate(getLocalTimeZone());

    if (!options.value.displayFormat) {
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(shown);
    }

    const [hours = 0, minutes = 0, seconds = 0] = (timeText.value ?? '').split(':').map(Number);

    shown.setHours(hours, minutes, seconds);

    return formatPhpDate(shown, options.value.displayFormat, store.locale);
});
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required" control-shows-loading>
        <div :class="cn('gap-2 grid', hasDate && hasTime && 'sm:grid-cols-[2fr_1fr]')">
            <PopoverRoot v-if="hasDate" v-model:open="open">
                <div class="relative">
                    <PopoverTrigger as-child>
                        <button
                            :id="id"
                            type="button"
                            :disabled="disabled"
                            :aria-invalid="error ? true : undefined"
                            :aria-describedby="describedBy"
                            :class="
                                cn(
                                    controlClass,
                                    'h-9 gap-2 px-3 flex cursor-pointer items-center text-left',
                                    !label && 'text-xq-muted-foreground',
                                )
                            "
                        >
                            <LoaderCircle
                                v-if="showLoading"
                                :class="spinnerClass"
                                aria-hidden="true"
                            />
                            <CalendarDays v-else class="size-4 shrink-0 opacity-60" />
                            <span class="truncate">{{
                                label ?? placeholder ?? t('Pick a date')
                            }}</span>
                        </button>
                    </PopoverTrigger>
                    <button
                        v-if="label && !required && !disabled"
                        type="button"
                        class="right-2 size-6 rounded-sm absolute top-1/2 flex -translate-y-1/2 cursor-pointer items-center justify-center text-xq-muted-foreground hover:text-xq-foreground"
                        :aria-label="t('Clear date')"
                        @click="date = undefined"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
                <PopoverPortal>
                    <PopoverContent
                        align="start"
                        :side-offset="4"
                        :class="cn(popoverClass, 'w-auto')"
                    >
                        <XqCalendar
                            :locale="store.locale"
                            v-model="date"
                            :min-value="parse(node.props?.min)"
                            :max-value="parse(node.props?.max)"
                            :is-date-unavailable="
                                disabledDates.size
                                    ? (day) => disabledDates.has(day.toString())
                                    : undefined
                            "
                        />
                    </PopoverContent>
                </PopoverPortal>
            </PopoverRoot>
            <XqTimeField
                v-if="hasTime"
                :id="hasDate ? `${id}-time` : id"
                v-model="timeText"
                :locale="store.locale"
                :seconds="!!options.seconds"
                :steps="options.steps"
                :disabled="disabled"
                :invalid="!!error"
                :described-by="describedBy"
                :label="fieldLabel"
            />
        </div>
    </XqFieldWrapper>
</template>
