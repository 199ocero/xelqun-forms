<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed, inject, provide, ref } from 'vue';
import { HEADING_LEVEL } from '../../composables/context';
import { gridStyle } from '../../composables/layout';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import XqNode from '../XqNode.vue';

const props = defineProps<{ node: SchemaNode }>();
const options = computed(() => props.node.props ?? {});
const collapsed = ref<boolean>(!!props.node.props?.collapsed);
const hasHeader = computed(() => !!(props.node.label || options.value.description));
const headingLevel = inject(HEADING_LEVEL, 2);

provide(HEADING_LEVEL, Math.min(headingLevel + 1, 6));
</script>

<template>
    <section
        class="shadow-xs rounded-xq-lg border border-xq-border bg-xq-card text-xq-card-foreground"
    >
        <header
            v-if="hasHeader"
            :class="
                cn(
                    'gap-4 px-5 py-4 flex items-start justify-between',
                    !collapsed && 'border-b border-xq-border',
                )
            "
        >
            <div class="gap-1 grid">
                <component :is="`h${headingLevel}`" v-if="node.label" class="text-sm font-semibold">
                    {{ node.label }}
                </component>
                <p v-if="options.description" class="text-sm text-xq-muted-foreground">
                    {{ options.description }}
                </p>
            </div>
            <button
                v-if="options.collapsible"
                type="button"
                class="size-7 flex cursor-pointer items-center justify-center rounded-xq-md text-xq-muted-foreground hover:bg-xq-accent"
                :aria-expanded="!collapsed"
                :aria-label="collapsed ? 'Expand section' : 'Collapse section'"
                @click="collapsed = !collapsed"
            >
                <ChevronDown
                    :class="
                        cn(
                            'size-4 transition-transform motion-reduce:transition-none',
                            collapsed && '-rotate-90',
                        )
                    "
                />
            </button>
        </header>
        <div v-show="!collapsed" class="xq-grid p-5" :style="gridStyle(options.columns)">
            <XqNode v-for="child in node.children ?? []" :key="child.key" :node="child" />
        </div>
    </section>
</template>
