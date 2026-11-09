<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, inject, provide, ref } from 'vue';
import { HEADING_LEVEL, useFormStore } from '../../composables/context';
import { gridStyle } from '../../composables/layout';
import { usePersisted } from '../../composables/usePersisted';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import XqIcon from '../../ui/XqIcon.vue';
import XqNode from '../XqNode.vue';
import { useTranslator } from '../../composables/context';

const t = useTranslator();
const store = useFormStore();
const props = defineProps<{ node: SchemaNode }>();
const options = computed(() => props.node.props ?? {});
const collapsed = ref<boolean>(!!props.node.props?.collapsed);
const inSlot = (name: string) => (props.node.children ?? []).filter((child) => child.slot === name);
const fields = computed(() => (props.node.children ?? []).filter((child) => !child.slot));
const afterHeader = computed(() => inSlot('afterHeader'));
const footer = computed(() => inSlot('footer'));
const hasHeader = computed(
    () => !!(props.node.label || options.value.description || afterHeader.value.length),
);
const headingLevel = inject(HEADING_LEVEL, 2);

usePersisted(
    `xq-section:${store.id}:${props.node.key}`,
    collapsed,
    () => !!options.value.persistCollapsed && !!options.value.collapsible,
    (saved): saved is boolean => typeof saved === 'boolean',
);

provide(HEADING_LEVEL, Math.min(headingLevel + 1, 6));
</script>

<template>
    <div
        :class="
            cn(
                options.aside &&
                    hasHeader &&
                    'gap-x-8 gap-y-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] grid',
            )
        "
    >
        <div v-if="options.aside && hasHeader" class="gap-1 grid content-start">
            <component
                :is="`h${headingLevel}`"
                v-if="node.label"
                class="gap-2 text-sm font-semibold flex items-center"
            >
                <XqIcon v-if="options.icon" :name="options.icon" class="text-xq-muted-foreground" />
                {{ node.label }}
            </component>
            <p v-if="options.description" class="text-sm text-xq-muted-foreground">
                {{ options.description }}
            </p>
            <div v-if="afterHeader.length" class="gap-2 mt-2 flex flex-wrap items-center">
                <XqNode v-for="child in afterHeader" :key="child.key" :node="child" />
            </div>
        </div>
        <section
            class="shadow-xs rounded-xq-lg border border-xq-border bg-xq-card text-xq-card-foreground"
        >
            <header
                v-if="hasHeader && !options.aside"
                :class="
                    cn(
                        'gap-4 flex items-start justify-between',
                        options.compact ? 'px-4 py-2.5' : 'px-5 py-4',
                        !collapsed && 'border-b border-xq-border',
                    )
                "
            >
                <div class="gap-1 grid">
                    <component
                        :is="`h${headingLevel}`"
                        v-if="node.label"
                        class="gap-2 text-sm font-semibold flex items-center"
                    >
                        <XqIcon
                            v-if="options.icon"
                            :name="options.icon"
                            class="text-xq-muted-foreground"
                        />
                        {{ node.label }}
                    </component>
                    <p v-if="options.description" class="text-sm text-xq-muted-foreground">
                        {{ options.description }}
                    </p>
                </div>
                <div class="gap-2 flex shrink-0 items-center">
                    <XqNode v-for="child in afterHeader" :key="child.key" :node="child" />
                    <button
                        v-if="options.collapsible"
                        type="button"
                        class="size-7 flex cursor-pointer items-center justify-center rounded-xq-md text-xq-muted-foreground hover:bg-xq-accent"
                        :aria-expanded="!collapsed"
                        :aria-label="collapsed ? t('Expand section') : t('Collapse section')"
                        @click="collapsed = !collapsed"
                    >
                        <ChevronDown
                            :class="
                                cn(
                                    'size-4 transition-transform duration-(--xq-duration-slow) ease-(--xq-ease-out)',
                                    collapsed && '-rotate-90',
                                )
                            "
                        />
                    </button>
                </div>
            </header>
            <div
                class="grid transition-[grid-template-rows] duration-(--xq-duration-slow) ease-(--xq-ease-out)"
                :class="collapsed ? 'grid-rows-[0fr]' : 'grid-rows-[1fr]'"
                :inert="collapsed"
            >
                <div class="min-h-0 overflow-hidden">
                    <div
                        :class="cn('xq-grid', options.compact ? 'p-4' : 'p-5')"
                        :style="gridStyle(options.columns)"
                    >
                        <XqNode v-for="child in fields" :key="child.key" :node="child" />
                    </div>
                    <footer
                        v-if="footer.length"
                        :class="
                            cn(
                                'gap-2 flex flex-wrap items-center border-t border-xq-border',
                                options.compact ? 'px-4 py-2.5' : 'px-5 py-3',
                            )
                        "
                    >
                        <XqNode v-for="child in footer" :key="child.key" :node="child" />
                    </footer>
                </div>
            </div>
        </section>
    </div>
</template>
