<script setup lang="ts">
import { CircleAlert } from 'lucide-vue-next';
import { inject, onMounted, provide, ref } from 'vue';
import { provideFormStore } from '../composables/context';
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
        class?: string;
    }>(),
    { method: 'post', submitLabel: 'Save' },
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
const { processing, uploading, fatalError } = store;

provideFormStore(store);
provide(REGISTRY, inject(REGISTRY, null) ?? createRegistry());

const root = ref<HTMLFormElement | null>(null);

onMounted(() => {
    store.root.value = root.value;
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
        <div
            v-if="fatalError"
            role="alert"
            class="gap-2 px-3 py-2 text-sm flex items-start rounded-xq-md border border-xq-destructive/40 bg-xq-destructive/10 text-xq-destructive"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" />
            {{ fatalError }}
        </div>
        <div class="xq-grid">
            <XqNode v-for="node in store.components" :key="node.key" :node="node" />
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
