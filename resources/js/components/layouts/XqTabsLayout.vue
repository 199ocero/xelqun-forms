<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useFormStore } from '../../composables/context';
import { gridStyle } from '../../composables/layout';
import { usePanelErrors } from '../../composables/usePanelErrors';
import { useQueryStringPanel } from '../../composables/useQueryStringPanel';
import type { SchemaNode } from '../../schema/types';
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
</script>

<template>
    <XqTabs v-model="active" :unmount-on-hide="false">
        <XqTabsList>
            <XqTabsTrigger v-for="tab in tabs" :key="tab.key" :value="tab.key">
                {{ tab.label }}
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
