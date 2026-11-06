<script setup lang="ts">
import type { DateValue } from '@internationalized/date';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import {
    CalendarCell,
    CalendarCellTrigger,
    CalendarGrid,
    CalendarGridBody,
    CalendarGridHead,
    CalendarGridRow,
    CalendarHeadCell,
    CalendarHeader,
    CalendarHeading,
    CalendarNext,
    CalendarPrev,
    CalendarRoot,
} from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const props = defineProps<{
    minValue?: DateValue;
    maxValue?: DateValue;
    placeholder?: DateValue;
    isDateUnavailable?: (date: DateValue) => boolean;
    locale?: string;
    class?: HTMLAttributes['class'];
}>();
const model = defineModel<DateValue | undefined>();

const navClass =
    'inline-flex size-7 cursor-pointer items-center justify-center rounded-xq-md border border-xq-border bg-transparent opacity-70 hover:bg-xq-accent hover:opacity-100 disabled:pointer-events-none disabled:opacity-30';
</script>

<template>
    <CalendarRoot
        v-slot="{ grid, weekDays }"
        v-model="model as any"
        :min-value="minValue"
        :max-value="maxValue"
        :placeholder="placeholder"
        :locale="locale"
        :is-date-unavailable="isDateUnavailable"
        fixed-weeks
        weekday-format="short"
        data-slot="xq-calendar"
        :class="cn('p-3', props.class)"
    >
        <CalendarHeader class="relative flex w-full items-center justify-between">
            <CalendarPrev :class="navClass"><ChevronLeft class="size-4" /></CalendarPrev>
            <CalendarHeading class="text-sm font-medium" />
            <CalendarNext :class="navClass"><ChevronRight class="size-4" /></CalendarNext>
        </CalendarHeader>
        <div class="mt-3 gap-y-4 flex flex-col">
            <CalendarGrid
                v-for="month in grid"
                :key="month.value.toString()"
                class="w-full border-collapse"
            >
                <CalendarGridHead>
                    <CalendarGridRow class="flex">
                        <CalendarHeadCell
                            v-for="day in weekDays"
                            :key="day"
                            class="w-8 font-normal text-[0.75rem] text-xq-muted-foreground"
                        >
                            {{ day.slice(0, 2) }}
                        </CalendarHeadCell>
                    </CalendarGridRow>
                </CalendarGridHead>
                <CalendarGridBody>
                    <CalendarGridRow
                        v-for="(weekDates, index) in month.rows"
                        :key="index"
                        class="mt-1 flex w-full"
                    >
                        <CalendarCell
                            v-for="weekDate in weekDates"
                            :key="weekDate.toString()"
                            :date="weekDate"
                            class="p-0 text-sm relative text-center"
                        >
                            <CalendarCellTrigger
                                :day="weekDate"
                                :month="month.value"
                                class="size-8 text-sm font-normal data-[today]:font-semibold inline-flex cursor-pointer items-center justify-center rounded-xq-md outline-none hover:bg-xq-accent focus-visible:ring-[3px] focus-visible:ring-xq-ring/30 data-[disabled]:pointer-events-none data-[disabled]:opacity-30 data-[outside-view]:text-xq-muted-foreground/60 data-[selected]:bg-xq-primary data-[selected]:text-xq-primary-foreground data-[today]:text-xq-primary data-[selected]:data-[today]:text-xq-primary-foreground data-[unavailable]:line-through"
                            />
                        </CalendarCell>
                    </CalendarGridRow>
                </CalendarGridBody>
            </CalendarGrid>
        </div>
    </CalendarRoot>
</template>
