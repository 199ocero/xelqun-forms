<script lang="ts">
import type { FormStore } from '../core/store';

/**
 * What the button needs from whatever holds it. Outside a form, pass your own.
 */
export type ActionRunner = Pick<FormStore, 'mountAction' | 'runAction' | 'processing' | 't'>;

/** How the last run went, for as long as the button shows it. */
export type ActionOutcome = 'done' | 'failed' | null;
</script>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { useFormStore } from '../composables/context';
import { createFormStore, LOADING_INDICATOR_DELAY } from '../core/store';
import { cn, isOwnUrl } from '../lib/utils';
import type { SchemaNode } from '../schema/types';
import { actionSizes, actionVariants, outlinedActionClasses } from '../ui/classes';
import XqButton from '../ui/XqButton.vue';
import XqConfirmDialog from '../ui/XqConfirmDialog.vue';
import XqDialog from '../ui/XqDialog.vue';
import XqDialogFooter from '../ui/XqDialogFooter.vue';
import XqIcon from '../ui/XqIcon.vue';
import XqActionBadge from './XqActionBadge.vue';
import XqActionContent from './XqActionContent.vue';
import XqForm from './XqForm.vue';

const props = defineProps<{
    node: SchemaNode;
    runner?: ActionRunner;
    /** Keep the dialogs and leave the button out, for a menu that has its own. */
    headless?: boolean;
}>();
const emit = defineEmits<{ spinner: [showsSpinner: boolean]; outcome: [outcome: ActionOutcome] }>();
const store = props.runner ?? useFormStore();
const options = computed(() => props.node.props ?? {});
const running = ref(false);
const showsSpinner = ref(false);
const confirming = ref(false);
const modalOpen = ref(false);
const modal = shallowRef<FormStore | null>(null);
const outcome = ref<ActionOutcome>(null);
const root = ref<HTMLElement | null>(null);

const variant = computed(() =>
    options.value.style === 'link'
        ? 'link'
        : options.value.style === 'icon'
          ? 'ghost'
          : options.value.outlined
            ? 'outline'
            : (actionVariants[options.value.color] ?? 'outline'),
);

const size = computed(() => {
    const chosen = actionSizes[options.value.size] ?? actionSizes.sm;

    return options.value.style === 'icon' ? chosen.icon : chosen.button;
});

const buttonClass = computed(() =>
    options.value.outlined && options.value.style === 'button'
        ? outlinedActionClasses[options.value.color]
        : undefined,
);

// A new tab isn't a visit.
const isVisit = computed(() => !options.value.openInNewTab && isOwnUrl(options.value.url));

/** Long enough to register, and short enough to be ready for another click. */
const OUTCOME_DURATION = 1000;

let spinnerTimer: ReturnType<typeof setTimeout> | undefined;
let outcomeTimer: ReturnType<typeof setTimeout> | undefined;

function showOutcome(next: ActionOutcome): void {
    clearTimeout(outcomeTimer);
    outcome.value = next;
    emit('outcome', next);
    outcomeTimer = setTimeout(() => {
        outcome.value = null;
        emit('outcome', null);
    }, OUTCOME_DURATION);
}

// A quick action changes nothing on screen until it's done, so it can't flicker.
watch(running, (isRunning) => {
    clearTimeout(spinnerTimer);
    showsSpinner.value = false;

    if (isRunning) {
        spinnerTimer = setTimeout(() => (showsSpinner.value = true), LOADING_INDICATOR_DELAY);
    }
});

async function run(): Promise<void> {
    // Enter in a modal field reaches this without the button.
    if (running.value) {
        return;
    }

    running.value = true;

    const result = await store.runAction(props.node, modal.value ?? undefined);

    // A modal keeps its failure to itself, on the field or message that explains it.
    if (result || !modalOpen.value) {
        showOutcome(result ? 'done' : 'failed');
    }

    if (result) {
        modalOpen.value = false;
    }

    if (result?.redirect) {
        if (isOwnUrl(result.redirect)) {
            router.visit(result.redirect);
        } else {
            window.location.assign(result.redirect);
        }
    }

    running.value = false;
}

async function open(): Promise<void> {
    // Disabling the button would dim it and drop keyboard focus, so a second click lands here.
    if (running.value) {
        return;
    }

    if (!options.value.hasForm) {
        if (options.value.confirmation) {
            confirming.value = true;
        } else {
            await run();
        }

        return;
    }

    running.value = true;

    const form = await store.mountAction(props.node);

    if (form) {
        modal.value = createFormStore(form);
        modalOpen.value = true;
    }

    running.value = false;
}

function closeModal(): void {
    modal.value?.dispose();
    modal.value = null;
}

watch(modalOpen, (isOpen) => {
    if (!isOpen) {
        closeModal();
    }
});

watch(showsSpinner, (shows) => emit('spinner', shows));

defineExpose({ open });

/** Whether a keydown matches a binding such as mod+s or ctrl+shift+k. */
function matchesBinding(event: KeyboardEvent, binding: string): boolean {
    const parts = binding.toLowerCase().split('+');
    const key = parts.pop();
    const isMac = /mac|iphone|ipad/i.test(navigator.platform);
    const wants = (name: string) =>
        parts.includes(name) ||
        (name === 'meta' && parts.includes('command')) ||
        (name === 'alt' && parts.includes('option')) ||
        (parts.includes('mod') && name === (isMac ? 'meta' : 'ctrl'));

    return (
        event.key.toLowerCase() === key &&
        event.ctrlKey === wants('ctrl') &&
        event.metaKey === wants('meta') &&
        event.shiftKey === wants('shift') &&
        event.altKey === wants('alt')
    );
}

function isTyping(event: KeyboardEvent): boolean {
    const target = event.target as HTMLElement | null;

    return (
        !event.ctrlKey &&
        !event.metaKey &&
        !event.altKey &&
        !!target?.closest?.(
            'input, textarea, select, [contenteditable]:not([contenteditable="false"])',
        )
    );
}

function onKeydown(event: KeyboardEvent): void {
    const bindings: string[] = options.value.keyBindings ?? [];

    if (isTyping(event)) {
        return;
    }

    if (options.value.disabled || !bindings.some((binding) => matchesBinding(event, binding))) {
        return;
    }

    event.preventDefault();

    // The button already knows how to open, visit or confirm. In a menu there's none, so open.
    const button = root.value?.querySelector<HTMLElement>('[data-slot="xq-button"]');

    if (button) {
        button.click();
    } else {
        void open();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

onScopeDispose(() => {
    clearTimeout(spinnerTimer);
    clearTimeout(outcomeTimer);
    closeModal();
});
</script>

<template>
    <div ref="root" class="relative inline-flex" data-xq-action>
        <XqActionBadge
            v-if="!headless && options.badge != null"
            :badge="options.badge"
            :color="options.badgeColor"
        />
        <template v-if="!headless">
            <XqButton
                v-if="options.url"
                as-child
                :variant="variant"
                :size="size"
                :class="buttonClass"
                :title="options.tooltip"
                :aria-label="options.style === 'icon' ? options.label : undefined"
            >
                <component
                    :is="isVisit ? Link : 'a'"
                    :href="options.url"
                    :target="options.openInNewTab ? '_blank' : undefined"
                    :rel="options.openInNewTab ? 'noopener noreferrer' : undefined"
                >
                    <XqIcon v-if="options.icon" :name="options.icon" />
                    <template v-if="options.style !== 'icon'">{{ options.label }}</template>
                </component>
            </XqButton>
            <XqButton
                v-else
                type="button"
                :variant="variant"
                :size="size"
                :class="
                    cn(
                        buttonClass,
                        showsSpinner && 'pointer-events-none',
                        outcome === 'failed' && 'animate-xq-shake',
                    )
                "
                :disabled="!!options.disabled || store.processing.value"
                :title="options.tooltip"
                :aria-label="options.style === 'icon' ? options.label : undefined"
                :aria-busy="running || undefined"
                @click="open"
            >
                <XqActionContent :spinning="showsSpinner" :done="outcome === 'done'">
                    <XqIcon v-if="options.icon" :name="options.icon" />
                    <template v-if="options.style !== 'icon'">{{ options.label }}</template>
                </XqActionContent>
            </XqButton>
        </template>
        <XqConfirmDialog
            v-if="options.confirmation && !options.hasForm"
            v-model:open="confirming"
            :title="options.heading"
            :message="options.description ?? store.t('Are you sure?')"
            :icon="options.modalIcon"
            :icon-color="options.modalIconColor ?? options.color"
            :alignment="options.alignment"
            :footer-alignment="options.footerAlignment"
            :confirm-label="options.submitLabel ?? store.t('Confirm')"
            :cancel-label="options.cancelLabel"
            :confirm-variant="
                actionVariants[options.color] === 'destructive' ? 'destructive' : 'default'
            "
            @confirm="run"
        />
        <XqDialog
            v-if="modal"
            v-model:open="modalOpen"
            :title="options.heading"
            :description="options.description"
            :width="options.width"
            :icon="options.modalIcon"
            :icon-color="options.modalIconColor"
            :alignment="options.alignment"
            :close-button="options.closeButton !== false"
            :close-on-click-away="options.closeOnClickAway !== false"
            :slide-over="!!options.slideOver"
        >
            <XqForm :form="modal" @submit="run">
                <template #actions>
                    <XqDialogFooter sticky :alignment="options.footerAlignment">
                        <XqButton
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="modalOpen = false"
                        >
                            {{ options.cancelLabel ?? store.t('Cancel') }}
                        </XqButton>
                        <XqButton
                            type="submit"
                            size="sm"
                            :class="showsSpinner && 'pointer-events-none'"
                            :aria-busy="running || undefined"
                        >
                            <span :class="showsSpinner && 'invisible'">
                                {{ options.submitLabel ?? store.t('Save') }}
                            </span>
                            <LoaderCircle
                                v-if="showsSpinner"
                                class="motion-safe:animate-spin absolute"
                                aria-hidden="true"
                            />
                        </XqButton>
                    </XqDialogFooter>
                </template>
            </XqForm>
        </XqDialog>
    </div>
</template>
