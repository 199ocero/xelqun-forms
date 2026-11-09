<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useFormStore } from '../../composables/context';
import { gridStyle } from '../../composables/layout';
import { usePanelErrors } from '../../composables/usePanelErrors';
import { usePersisted } from '../../composables/usePersisted';
import { useQueryStringPanel } from '../../composables/useQueryStringPanel';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { actionBadgeClasses } from '../../ui/classes';
import XqIcon from '../../ui/XqIcon.vue';
import XqTabs from '../../ui/XqTabs.vue';
import XqTabsContent from '../../ui/XqTabsContent.vue';
import XqTabsList from '../../ui/XqTabsList.vue';
import XqTabsTrigger from '../../ui/XqTabsTrigger.vue';
import XqNode from '../XqNode.vue';

const props = defineProps<{ node: SchemaNode }>();
const store = useFormStore();
const tabs = computed(() => (props.node.children ?? []).filter((tab) => store.isVisible(tab)));
const active = ref<string>(
    tabs.value.find((tab) => tab.key === props.node.props?.startKey)?.key ??
        tabs.value[0]?.key ??
        '',
);

watch(tabs, (visible) => {
    if (!visible.some((tab) => tab.key === active.value)) {
        active.value = visible[0]?.key ?? '';
    }
});

const { errorCount } = usePanelErrors(tabs, (tab) => (active.value = tab.key));
useQueryStringPanel(active, tabs, () => props.node.props?.queryKey);

// A tab named in the URL wins over the one remembered.
usePersisted(
    `xq-tabs:${store.id}:${props.node.key}`,
    active,
    () => !!props.node.props?.persistTab && !props.node.props?.startKey,
    (saved): saved is string =>
        typeof saved === 'string' && tabs.value.some((tab) => tab.key === saved),
);

const vertical = computed(() => !!props.node.props?.vertical);
</script>

<template>
    <XqTabs
        v-model="active"
        :unmount-on-hide="false"
        :orientation="vertical ? 'vertical' : 'horizontal'"
        :class="
            cn(
                vertical && 'sm:grid sm:grid-cols-[auto_minmax(0,1fr)] sm:items-start sm:gap-6',
                node.props?.contained &&
                    'p-4 shadow-xs rounded-xq-lg border border-xq-border bg-xq-card text-xq-card-foreground',
            )
        "
    >
        <XqTabsList :vertical="vertical">
            <XqTabsTrigger v-for="tab in tabs" :key="tab.key" :value="tab.key">
                <XqIcon v-if="tab.props?.icon" :name="tab.props.icon" />
                {{ tab.label }}
                <span
                    v-if="tab.props?.badge != null && !errorCount(tab)"
                    :class="
                        cn(
                            'px-1.5 font-semibold leading-4 min-w-4 rounded-full text-center text-[0.65rem] tabular-nums',
                            actionBadgeClasses[tab.props?.badgeColor ?? 'gray'] ??
                                actionBadgeClasses.gray,
                        )
                    "
                    data-xq-tab-badge
                >
                    {{ tab.props.badge }}
                </span>
                <span
                    v-if="errorCount(tab)"
                    class="size-4 font-semibold inline-flex items-center justify-center rounded-full bg-xq-destructive text-[0.65rem] text-xq-destructive-foreground"
                >
                    {{ errorCount(tab) }}
                </span>
            </XqTabsTrigger>
        </XqTabsList>
        <XqTabsContent v-for="tab in tabs" :key="tab.key" :value="tab.key">
            <div class="xq-grid" :style="gridStyle(tab.props?.columns)">
                <XqNode v-for="child in tab.children ?? []" :key="child.key" :node="child" />
            </div>
        </XqTabsContent>
    </XqTabs>
</template>
