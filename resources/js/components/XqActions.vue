<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleAlert } from 'lucide-vue-next';
import { computed, onScopeDispose, shallowRef, watch } from 'vue';
import { createFormStore } from '../core/store';
import type { FormSchema } from '../schema/types';
import XqAction from './XqAction.vue';
import XqActionGroup from './XqActionGroup.vue';
import type { ActionRunner } from './XqAction.vue';

/**
 * The buttons of an ActionSet, with no form around them.
 */
const props = defineProps<{ actions: FormSchema }>();
const store = shallowRef(createFormStore(props.actions));
const visibleActions = computed(() => props.actions.components.filter((node) => !node.hidden));

/**
 * Resolves once the page has the new data, so the button stays busy until then.
 *
 * shortcut: a global "before" listener that cancels this leaves the button busy.
 * Forms' own unsaved changes alert ignores reloads, so only an app's own could.
 */
function reloadPage(): Promise<void> {
    return new Promise((resolve) => router.reload({ onFinish: () => resolve() }));
}

// One runner for good: a new one, or a new key, would rebuild the buttons and lose focus.
const runner: ActionRunner = {
    get processing() {
        return store.value.processing;
    },
    t: (...args) => store.value.t(...args),
    mountAction: (node) => store.value.mountAction(node),
    async runAction(node, modal) {
        const result = await store.value.runAction(node, modal);

        if (result && !result.redirect) {
            await reloadPage();
        }

        return result;
    },
};

// With no fields to land on, a failure has to show here.
const failure = computed(() => {
    const { errors, fatalError, liveError, t } = store.value;

    return (
        fatalError.value ??
        (liveError.value ? t('That action could not run.') : Object.values(errors)[0])
    );
});

watch(
    () => props.actions,
    (next) => {
        store.value.dispose();
        store.value = createFormStore(next);
    },
);

onScopeDispose(() => store.value.dispose());
</script>

<template>
    <div class="gap-2 flex flex-wrap items-center" data-xq-actions>
        <component
            :is="node.type === 'action-group' ? XqActionGroup : XqAction"
            v-for="node in visibleActions"
            :key="node.key"
            :node="node"
            :runner="runner"
        />
        <p
            v-if="failure"
            role="alert"
            class="gap-2 text-sm flex basis-full items-start text-xq-destructive"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {{ failure }}
        </p>
    </div>
</template>
