<script setup lang="ts">
import { CircleAlert } from 'lucide-vue-next';
import { computed, inject, onMounted, provide, ref } from 'vue';
import { HEADING_LEVEL, provideFormStore } from '../composables/context';
import { REGISTRY } from '../composables/registry';
import { useXqForm } from '../composables/useXqForm';
import type { FormStore } from '../core/store';
import { cn } from '../lib/utils';
import type { FormSchema } from '../schema/types';
import XqButton from '../ui/XqButton.vue';
import { createRegistry } from './defaults';
import XqNode from './XqNode.vue';

const props = withDefaults(
    defineProps<{
        form: FormStore | FormSchema;
        action?: string;
        method?: 'post' | 'put' | 'patch';
        submitLabel?: string;
        preserveState?: boolean | 'errors';
        /** Heading level of top-level section titles. Set it to fit the page around the form. */
        headingLevel?: 2 | 3 | 4 | 5 | 6;
        class?: string;
    }>(),
    { method: 'post', submitLabel: 'Save', headingLevel: 2, preserveState: 'errors' },
);
const emit = defineEmits<{
    submit: [store: FormStore];
    success: [page: unknown];
    error: [errors: Record<string, string>];
}>();

function isStore(value: FormStore | FormSchema): value is FormStore {
    return 'state' in value && 'submit' in value && typeof value.submit === 'function';
}

const store = isStore(props.form) ? props.form : useXqForm(props.form);
const { processing, uploading, fatalError, liveError } = store;
const syncing = store.queue.busy;
const errorCount = computed(() => Object.keys(store.errors).length);

provideFormStore(store);
provide(HEADING_LEVEL, props.headingLevel);
provide(REGISTRY, inject(REGISTRY, null) ?? createRegistry());

const root = ref<HTMLFormElement | null>(null);

onMounted(() => {
    store.root.value = root.value;

    const first = [...store.nodes.values()].find(
        (node) => node.autofocus && node.path && store.isVisible(node),
    );

    if (first) {
        void store.focusPath(first.path!);
    }
});

function submit(): void {
    if (!props.action) {
        emit('submit', store);

        return;
    }

    void store.submit(props.action, {
        method: props.method,
        preserveState: props.preserveState,
        onSuccess: (page) => emit('success', page),
        onError: (errors) => emit('error', errors),
    });
}
</script>

<template>
    <form
        ref="root"
        :class="cn('xq-form gap-6 grid', props.class)"
        :data-xq-form="store.id"
        novalidate
        @submit.prevent="submit"
    >
        <div class="xq-grid">
            <XqNode v-for="node in store.components" :key="node.key" :node="node" />
        </div>
        <p role="status" class="sr-only">
            <template v-if="errorCount">
                {{ errorCount }} {{ errorCount === 1 ? 'field needs' : 'fields need' }} attention.
            </template>
        </p>
        <div
            v-if="fatalError"
            role="alert"
            class="bottom-4 gap-2 px-3 py-2 text-sm shadow-lg sticky z-10 flex items-start rounded-xq-md border border-xq-destructive/40 bg-xq-card text-xq-destructive"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" />
            {{ fatalError }}
        </div>
        <div
            v-if="liveError"
            role="alert"
            class="bottom-4 gap-3 px-3 py-2 text-sm shadow-lg sticky z-10 flex items-center justify-between rounded-xq-md border border-xq-destructive/40 bg-xq-card text-xq-destructive"
        >
            <span class="gap-2 flex items-start">
                <CircleAlert class="mt-0.5 size-4 shrink-0" />
                {{ liveError }}
            </span>
            <XqButton
                type="button"
                variant="outline"
                size="sm"
                :disabled="syncing"
                @click="store.queue.retry()"
            >
                Try again
            </XqButton>
        </div>
        <slot
            name="actions"
            :store="store"
            :processing="processing"
            :uploading="uploading"
            :submit="submit"
        >
            <div v-if="action" class="gap-3 flex items-center justify-end">
                <span v-if="uploading" class="text-xs text-xq-muted-foreground"
                    >Waiting for uploads…</span
                >
                <XqButton type="submit" :disabled="processing">
                    {{ processing ? 'Saving…' : submitLabel }}
                </XqButton>
            </div>
        </slot>
    </form>
</template>
