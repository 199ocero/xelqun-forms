<script setup lang="ts">
import { getLocalTimeZone, parseDate } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import { CalendarDays, LoaderCircle, X } from 'lucide-vue-next';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';
import { computed, ref } from 'vue';
import { useField } from '../../composables/useField';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { controlClass, popoverClass, spinnerClass } from '../../ui/classes';
import XqCalendar from '../../ui/XqCalendar.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';

const props = defineProps<{ node: SchemaNode }>();
const { value, error, placeholder, describedBy, showLoading, required, disabled, id, onBlur } =
    useField<string | null>(props.node);
const open = ref(false);

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
        value.value = next ? next.toString() : null;
        open.value = false;
        onBlur();
    },
});

const label = computed(() =>
    date.value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
              date.value.toDate(getLocalTimeZone()),
          )
        : null,
);
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required" control-shows-loading>
        <PopoverRoot v-model:open="open">
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
                        <LoaderCircle v-if="showLoading" :class="spinnerClass" aria-hidden="true" />
                        <CalendarDays v-else class="size-4 shrink-0 opacity-60" />
                        <span class="truncate">{{ label ?? placeholder ?? 'Pick a date' }}</span>
                    </button>
                </PopoverTrigger>
                <button
                    v-if="label && !required && !disabled"
                    type="button"
                    class="right-2 size-6 rounded-sm absolute top-1/2 flex -translate-y-1/2 cursor-pointer items-center justify-center text-xq-muted-foreground hover:text-xq-foreground"
                    aria-label="Clear date"
                    @click="date = undefined"
                >
                    <X class="size-3.5" />
                </button>
            </div>
            <PopoverPortal>
                <PopoverContent align="start" :side-offset="4" :class="cn(popoverClass, 'w-auto')">
                    <XqCalendar
                        v-model="date"
                        :min-value="parse(node.props?.min)"
                        :max-value="parse(node.props?.max)"
                    />
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
    </XqFieldWrapper>
</template>
