<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuTrigger,
} from 'reka-ui';
import { computed, ref } from 'vue';
import type { ComponentPublicInstance } from 'vue';
import { useFormStore } from '../composables/context';
import { cn, isOwnUrl } from '../lib/utils';
import type { SchemaNode } from '../schema/types';
import { actionVariants, optionClass, popoverClass } from '../ui/classes';
import XqButton from '../ui/XqButton.vue';
import XqIcon from '../ui/XqIcon.vue';
import XqAction from './XqAction.vue';
import type { ActionOutcome } from './XqAction.vue';
import XqActionContent from './XqActionContent.vue';
import type { ActionRunner } from './XqAction.vue';

/**
 * A menu of actions. Closing a menu destroys what's in it, so each item only
 * asks an XqAction outside the menu to open, and the dialogs live there.
 */
const props = defineProps<{ node: SchemaNode; runner?: ActionRunner }>();
const form = props.runner ? null : useFormStore();
const store = props.runner ?? form!;
const options = computed(() => props.node.props ?? {});
const items = computed(() =>
    (props.node.children ?? []).filter((item) => form?.isVisible(item) ?? !item.hidden),
);
const headlessActions = computed(() => items.value.filter((item) => !item.props?.url));
const hasLabel = computed(() => !!options.value.label);
const showsSpinner = ref(false);
const outcome = ref<ActionOutcome>(null);
const actions = new Map<string, { open: () => Promise<void> }>();
let selected: string | null = null;

function remember(key: string, action: Element | ComponentPublicInstance | null): void {
    if (action) {
        actions.set(key, action as unknown as { open: () => Promise<void> });
    } else {
        actions.delete(key);
    }
}

/**
 * Waits for the menu to give focus back to its button, so that a dialog, which
 * returns focus to wherever it found it, comes back to the button too.
 */
function openSelected(): void {
    if (selected) {
        void actions.get(selected)?.open();
        selected = null;
    }
}
</script>

<template>
    <div class="inline-flex" data-xq-action-group>
        <DropdownMenuRoot v-if="items.length > 0" :modal="false">
            <DropdownMenuTrigger as-child>
                <XqButton
                    type="button"
                    :variant="hasLabel ? (actionVariants[options.color] ?? 'outline') : 'ghost'"
                    :size="hasLabel ? 'sm' : 'icon-sm'"
                    :class="
                        cn(
                            showsSpinner && 'pointer-events-none',
                            outcome === 'failed' && 'animate-xq-shake',
                        )
                    "
                    :disabled="store.processing.value"
                    :title="options.tooltip"
                    :aria-label="hasLabel ? undefined : store.t('Actions')"
                    :aria-busy="showsSpinner || undefined"
                >
                    <XqActionContent :spinning="showsSpinner" :done="outcome === 'done'">
                        <XqIcon :name="options.icon ?? 'ellipsis-vertical'" />
                        <template v-if="hasLabel">{{ options.label }}</template>
                    </XqActionContent>
                </XqButton>
            </DropdownMenuTrigger>
            <DropdownMenuPortal>
                <DropdownMenuContent
                    align="end"
                    :side-offset="4"
                    :class="cn(popoverClass, 'p-1 min-w-40')"
                    @close-auto-focus="openSelected"
                >
                    <template v-for="item in items" :key="item.key">
                        <DropdownMenuItem
                            v-if="item.props?.url"
                            as-child
                            :class="cn(optionClass, 'pr-2')"
                        >
                            <component
                                :is="
                                    !item.props.openInNewTab && isOwnUrl(item.props.url)
                                        ? Link
                                        : 'a'
                                "
                                :href="item.props.url"
                                :target="item.props.openInNewTab ? '_blank' : undefined"
                                :rel="item.props.openInNewTab ? 'noopener noreferrer' : undefined"
                            >
                                <XqIcon v-if="item.props.icon" :name="item.props.icon" />
                                {{ item.props.label }}
                            </component>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-else
                            :disabled="!!item.props?.disabled"
                            :class="
                                cn(
                                    optionClass,
                                    'pr-2',
                                    item.props?.color === 'danger' &&
                                        'text-xq-destructive data-[highlighted]:text-xq-destructive',
                                )
                            "
                            @select="selected = item.key"
                        >
                            <XqIcon v-if="item.props?.icon" :name="item.props.icon" />
                            {{ item.props?.label }}
                        </DropdownMenuItem>
                    </template>
                </DropdownMenuContent>
            </DropdownMenuPortal>
        </DropdownMenuRoot>
        <div hidden>
            <XqAction
                v-for="item in headlessActions"
                :key="item.key"
                :ref="(action) => remember(item.key, action)"
                :node="item"
                :runner="store"
                headless
                @spinner="showsSpinner = $event"
                @outcome="outcome = $event"
            />
        </div>
    </div>
</template>
