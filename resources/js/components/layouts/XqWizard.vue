<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, ChevronLeft, ChevronRight, LoaderCircle } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useFormStore } from '../../composables/context';
import { gridStyle } from '../../composables/layout';
import { usePanelErrors } from '../../composables/usePanelErrors';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import XqButton from '../../ui/XqButton.vue';
import XqIcon from '../../ui/XqIcon.vue';
import XqNode from '../XqNode.vue';

const props = defineProps<{ node: SchemaNode }>();
const store = useFormStore();
const { t, validating } = store;
const options = computed(() => props.node.props ?? {});
const steps = computed(() => (props.node.children ?? []).filter((step) => store.isVisible(step)));
const startKey = options.value.startKey;
const active = ref<string>(
    steps.value.find((step) => step.key === startKey)?.key ?? steps.value[0]?.key ?? '',
);
const activeIndex = computed(() => steps.value.findIndex((step) => step.key === active.value));
const panels = ref<Record<string, HTMLElement>>({});

const { errorCount } = usePanelErrors(steps, (step) => (active.value = step.key));

watch(steps, (visible) => {
    if (!visible.some((step) => step.key === active.value)) {
        active.value = visible[0]?.key ?? '';
    }
});

watch(active, (key) => {
    const slug = steps.value.find((step) => step.key === key)?.props?.slug;

    if (options.value.queryKey && slug) {
        const url = new URL(window.location.href);

        url.searchParams.set(options.value.queryKey, slug);
        router.replace({
            url: `${url.pathname}${url.search}`,
            preserveState: true,
            preserveScroll: true,
        });
    }
});

async function show(key: string): Promise<void> {
    active.value = key;
    await nextTick();
    panels.value[key]?.focus({ preventScroll: true });
    panels.value[key]?.scrollIntoView?.({ block: 'nearest' });
}

async function next(): Promise<void> {
    const step = steps.value[activeIndex.value];

    if (!step || validating.value) {
        return;
    }

    if (options.value.skippable || (await store.validateStep(step))) {
        // Looked up now: what a step's hooks set can hide the step that was next.
        const following = steps.value[activeIndex.value + 1];

        if (following) {
            await show(following.key);
        }
    }
}

function canOpen(index: number): boolean {
    return !!options.value.skippable || index <= activeIndex.value;
}

const stepper = { hasNext: () => activeIndex.value < steps.value.length - 1, next };

onMounted(() => store.steppers.add(stepper));
onBeforeUnmount(() => store.steppers.delete(stepper));
</script>

<template>
    <div class="gap-5 grid">
        <ol class="gap-2 sm:flex-row sm:gap-4 flex flex-col">
            <li v-for="(step, index) in steps" :key="step.key" class="min-w-0 flex-1">
                <button
                    type="button"
                    class="gap-2.5 flex w-full cursor-pointer items-center rounded-xq-md text-start disabled:cursor-default"
                    :aria-current="step.key === active ? 'step' : undefined"
                    :disabled="!canOpen(index)"
                    @click="show(step.key)"
                >
                    <span
                        :class="
                            cn(
                                'size-7 text-xs font-medium flex shrink-0 items-center justify-center rounded-full border border-xq-border',
                                step.key === active &&
                                    'border-xq-primary bg-xq-primary text-xq-primary-foreground',
                                index < activeIndex && 'border-xq-primary text-xq-primary',
                                errorCount(step) > 0 && 'border-xq-destructive text-xq-destructive',
                            )
                        "
                    >
                        <XqIcon
                            v-if="index < activeIndex && step.props?.completedIcon"
                            :name="step.props.completedIcon"
                        />
                        <Check v-else-if="index < activeIndex" class="size-4" aria-hidden="true" />
                        <XqIcon v-else-if="step.props?.icon" :name="step.props.icon" />
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    <span class="min-w-0 grid">
                        <span class="text-sm font-medium truncate">{{ step.label }}</span>
                        <span
                            v-if="step.props?.description"
                            class="text-xs truncate text-xq-muted-foreground"
                        >
                            {{ step.props.description }}
                        </span>
                    </span>
                </button>
            </li>
        </ol>
        <section
            v-for="step in steps"
            :key="step.key"
            :ref="(element) => (panels[step.key] = element as HTMLElement)"
            :hidden="step.key !== active"
            :aria-label="step.label ?? undefined"
            tabindex="-1"
            class="outline-none"
        >
            <div class="xq-grid" :style="gridStyle(step.props?.columns)">
                <XqNode v-for="child in step.children ?? []" :key="child.key" :node="child" />
            </div>
        </section>
        <div class="gap-3 flex items-center justify-between">
            <XqButton
                v-if="activeIndex > 0"
                type="button"
                variant="outline"
                size="sm"
                data-xq-wizard-back
                @click="show(steps[activeIndex - 1].key)"
            >
                <ChevronLeft />{{ t('Back') }}
            </XqButton>
            <XqButton
                v-if="activeIndex < steps.length - 1"
                type="button"
                size="sm"
                class="ms-auto"
                data-xq-wizard-next
                :disabled="validating"
                @click="next"
            >
                {{ t('Next') }}
                <LoaderCircle
                    v-if="validating"
                    class="motion-safe:animate-spin"
                    aria-hidden="true"
                />
                <ChevronRight v-else />
            </XqButton>
        </div>
    </div>
</template>
