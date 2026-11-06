<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next';
import { computed, inject } from 'vue';
import XqFieldSlot from '../XqFieldSlot.vue';
import { FUSED_GROUP } from '../../composables/context';
import { useField } from '../../composables/useField';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import XqLabel from '../../ui/XqLabel.vue';
import XqSwitch from '../../ui/XqSwitch.vue';

const props = defineProps<{ node: SchemaNode }>();
const { store, value, error, label, hint, loading, showLoading, required, disabled, id } =
    useField<boolean>(props.node);
const fused = inject(FUSED_GROUP, false);
const hasHint = computed(() => !!(props.node.props?.description || hint.value));
const describedBy = computed(() =>
    error.value ? `${id}-error` : hasHint.value ? `${id}-hint` : undefined,
);
</script>

<template>
    <div class="gap-2 grid" :data-xq-path="node.path" :aria-busy="loading || undefined">
        <XqFieldSlot :node="node" name="aboveContent" />
        <div class="gap-3 flex items-start">
            <XqFieldSlot :node="node" name="beforeContent" />
            <XqFieldSlot :node="node" name="prefixAction" />
            <XqSwitch
                :id="id"
                v-model="value"
                :disabled="disabled"
                :aria-invalid="error ? true : undefined"
                :aria-describedby="describedBy"
            />
            <div class="gap-1.5 grid">
                <XqFieldSlot :node="node" name="aboveLabel" />
                <XqFieldSlot :node="node" name="beforeLabel" />
                <XqLabel
                    :for="id"
                    :class="cn('gap-0.5 leading-5 cursor-pointer', node.hiddenLabel && 'sr-only')"
                >
                    {{ label }}
                    <span v-if="required" class="text-xq-destructive" aria-hidden="true">*</span>
                    <span v-if="required" class="sr-only">{{ store.t('(required)') }}</span>
                    <LoaderCircle
                        v-if="showLoading"
                        class="ml-1 size-3 motion-safe:animate-spin text-xq-muted-foreground"
                        aria-hidden="true"
                    />
                </XqLabel>
                <XqFieldSlot :node="node" name="afterLabel" />
                <XqFieldSlot :node="node" name="belowLabel" />
                <p v-if="hasHint" :id="`${id}-hint`" class="text-xs text-xq-muted-foreground">
                    {{ node.props?.description ?? hint }}
                </p>
            </div>
            <XqFieldSlot class="ms-auto" :node="node" name="suffixAction" />
            <XqFieldSlot :node="node" name="afterContent" />
        </div>
        <XqFieldSlot :node="node" name="belowContent" />
        <XqFieldSlot :node="node" name="aboveErrorMessage" />
        <p
            v-if="error && !fused"
            :id="`${id}-error`"
            class="text-xs font-medium text-xq-destructive"
        >
            {{ error }}
        </p>
        <XqFieldSlot :node="node" name="belowErrorMessage" />
    </div>
</template>
