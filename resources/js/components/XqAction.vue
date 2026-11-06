<script lang="ts">
import type { FormStore } from '../core/store';

/**
 * What the button needs from whatever holds it. Outside a form, pass your own.
 */
export type ActionRunner = Pick<FormStore, 'mountAction' | 'runAction' | 'processing' | 't'>;
</script>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { useFormStore } from '../composables/context';
import { createFormStore } from '../core/store';
import type { SchemaNode } from '../schema/types';
import XqButton from '../ui/XqButton.vue';
import XqConfirmDialog from '../ui/XqConfirmDialog.vue';
import XqDialog from '../ui/XqDialog.vue';
import XqIcon from '../ui/XqIcon.vue';
import XqForm from './XqForm.vue';

const props = defineProps<{ node: SchemaNode; runner?: ActionRunner }>();
const store = props.runner ?? useFormStore();
const options = computed(() => props.node.props ?? {});
const running = ref(false);
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

// Inertia can only visit its own app, and a new tab isn't a visit.
const isVisit = computed(() => {
    if (options.value.openInNewTab) {
        return false;
    }

    const url: string = options.value.url;

    return typeof window === 'undefined'
        ? url.startsWith('/')
        : new URL(url, window.location.href).origin === window.location.origin;
});

async function run(): Promise<void> {
    // Enter in a modal field reaches this without the button.
    if (running.value) {
        return;
    }

    running.value = true;

    if (await store.runAction(props.node, modal.value ?? undefined)) {
        modalOpen.value = false;
    }

    running.value = false;
}

async function open(): Promise<void> {
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

onScopeDispose(closeModal);
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
            :disabled="!!options.disabled || running || store.processing.value"
            :title="options.tooltip"
            :aria-label="options.style === 'icon' ? options.label : undefined"
            @click="open"
        >
            <LoaderCircle v-if="running" class="motion-safe:animate-spin" aria-hidden="true" />
            <XqIcon v-else-if="options.icon" :name="options.icon" />
            <template v-if="options.style !== 'icon'">{{ options.label }}</template>
        </XqButton>
        <XqConfirmDialog
            v-if="options.confirmation && !options.hasForm"
            v-model:open="confirming"
            :title="options.heading"
            :message="options.description ?? store.t('Are you sure?')"
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
        >
            <XqForm :form="modal" @submit="run">
                <template #actions>
                    <div class="gap-2 flex justify-end">
                        <XqButton
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="modalOpen = false"
                        >
                            {{ options.cancelLabel ?? store.t('Cancel') }}
                        </XqButton>
                        <XqButton type="submit" size="sm" :disabled="running">
                            <LoaderCircle
                                v-if="running"
                                class="motion-safe:animate-spin"
                                aria-hidden="true"
                            />
                            {{ options.submitLabel ?? store.t('Save') }}
                        </XqButton>
                    </div>
                </template>
            </XqForm>
        </XqDialog>
    </div>
</template>
