<script setup lang="ts">
import { computed, ref } from 'vue';
import { useFormStore, useTranslator } from '../../composables/context';
import { gridStyle } from '../../composables/layout';
import { eachNode } from '../../core/patch';
import type { SchemaNode } from '../../schema/types';
import XqButton from '../../ui/XqButton.vue';
import XqNode from '../XqNode.vue';

const props = defineProps<{ node: SchemaNode }>();
const store = useFormStore();
const t = useTranslator();
const editingByHand = ref(false);
const preview = computed<string | null>(() => props.node.props?.preview ?? null);
// An error has to be seen, so a block with one always shows its fields.
const hasError = computed(() => {
    let found = false;

    eachNode(props.node.children ?? [], (child) => {
        found ||= !!(child.path && store.errors[child.path]);
    });

    return found;
});
const editing = computed(() => editingByHand.value || hasError.value);
</script>

<template>
    <div class="gap-3 grid">
        <template v-if="preview !== null">
            <!-- Rendered on the server by the form's author, who escapes what users typed. -->
            <div v-show="!editing" data-xq-block-preview v-html="preview" />
            <div>
                <XqButton
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="editingByHand = !editing"
                >
                    {{ editing ? t('Show preview') : t('Edit block') }}
                </XqButton>
            </div>
        </template>
        <div
            v-show="preview === null || editing"
            class="xq-grid"
            :style="gridStyle(node.props?.columns)"
        >
            <XqNode v-for="child in node.children ?? []" :key="child.key" :node="child" />
        </div>
    </div>
</template>
