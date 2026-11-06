<script lang="ts">
import type { FormStore } from '../core/store';

/**
 * What the button needs from whatever holds it. Outside a form, pass your own.
 */
export type ActionRunner = Pick<FormStore, 'mountAction' | 'runAction' | 'processing' | 't'>;
</script>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { useFormStore } from '../composables/context';
import { createFormStore, LOADING_INDICATOR_DELAY } from '../core/store';
import { cn } from '../lib/utils';
import type { SchemaNode } from '../schema/types';
import XqButton from '../ui/XqButton.vue';
import XqConfirmDialog from '../ui/XqConfirmDialog.vue';
import XqDialog from '../ui/XqDialog.vue';
import XqDialogFooter from '../ui/XqDialogFooter.vue';
import XqIcon from '../ui/XqIcon.vue';
import XqForm from './XqForm.vue';

const props = defineProps<{ node: SchemaNode; runner?: ActionRunner }>();
const store = props.runner ?? useFormStore();
const options = computed(() => props.node.props ?? {});
const running = ref(false);
const showsSpinner = ref(false);
const confirming = ref(false);
const modalOpen = ref(false);
const modal = shallowRef<FormStore | null>(null);

const variants: Record<string, 'default' | 'destructive' | 'outline'> = {
    primary: 'default',
    danger: 'destructive',
    gray: 'outline',
};
const variant = computed(() =>
    options.value.style === 'link'
        ? 'link'
        : options.value.style === 'icon'
          ? 'ghost'
          : (variants[options.value.color] ?? 'outline'),
);

// Inertia can only visit its own app.
function isOwnUrl(url: string): boolean {
    return typeof window === 'undefined'
        ? url.startsWith('/')
        : new URL(url, window.location.href).origin === window.location.origin;
}

// A new tab isn't a visit.
const isVisit = computed(() => !options.value.openInNewTab && isOwnUrl(options.value.url));

let spinnerTimer: ReturnType<typeof setTimeout> | undefined;

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

onScopeDispose(() => {
    clearTimeout(spinnerTimer);
    closeModal();
});
</script>

<template>
    <div class="inline-flex" data-xq-action>
        <XqButton
            v-if="options.url"
            as-child
            :variant="variant"
            :size="options.style === 'icon' ? 'icon-sm' : 'sm'"
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
            :size="options.style === 'icon' ? 'icon-sm' : 'sm'"
            :class="showsSpinner && 'pointer-events-none'"
            :disabled="!!options.disabled || store.processing.value"
            :title="options.tooltip"
            :aria-label="options.style === 'icon' ? options.label : undefined"
            :aria-busy="running || undefined"
            @click="open"
        >
            <span :class="cn('gap-1.5 inline-flex items-center', showsSpinner && 'invisible')">
                <XqIcon v-if="options.icon" :name="options.icon" />
                <template v-if="options.style !== 'icon'">{{ options.label }}</template>
            </span>
            <LoaderCircle
                v-if="showsSpinner"
                class="motion-safe:animate-spin absolute"
                aria-hidden="true"
            />
        </XqButton>
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
            :confirm-variant="variants[options.color] === 'destructive' ? 'destructive' : 'default'"
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
