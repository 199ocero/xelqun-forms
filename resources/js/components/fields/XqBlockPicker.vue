<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';
import { computed, ref } from 'vue';
import { cn } from '../../lib/utils';
import type { BlockOption } from '../../schema/types';
import { groupLabelClass, popoverClass } from '../../ui/classes';
import XqButton from '../../ui/XqButton.vue';
import XqIcon from '../../ui/XqIcon.vue';

const props = defineProps<{
    blocks: BlockOption[];
    label: string;
    disabled?: boolean;
    used: Record<string, number>;
}>();
const emit = defineEmits<{ pick: [block: string] }>();
const open = ref(false);

const groups = computed(() => {
    const found = new Map<string | null, BlockOption[]>();

    for (const block of props.blocks) {
        found.set(block.group, [...(found.get(block.group) ?? []), block]);
    }

    return [...found.entries()].sort(([a], [b]) => Number(a !== null) - Number(b !== null));
});

function isFull(block: BlockOption): boolean {
    return block.maxItems !== null && (props.used[block.name] ?? 0) >= block.maxItems;
}

function pick(block: BlockOption): void {
    open.value = false;
    emit('pick', block.name);
}
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger as-child>
            <XqButton type="button" variant="outline" size="sm" :disabled="disabled" data-xq-add>
                <Plus />
                {{ label }}
            </XqButton>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent align="start" :side-offset="4" :class="cn(popoverClass, 'w-56 p-1')">
                <div v-for="[group, items] in groups" :key="group ?? ''">
                    <p v-if="group" :class="groupLabelClass">{{ group }}</p>
                    <button
                        v-for="block in items"
                        :key="block.name"
                        type="button"
                        class="gap-2 px-2 py-1.5 text-sm flex w-full cursor-pointer items-center rounded-xq-sm hover:bg-xq-accent disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isFull(block)"
                        :data-xq-block="block.name"
                        @click="pick(block)"
                    >
                        <XqIcon v-if="block.icon" :name="block.icon" />
                        {{ block.label }}
                    </button>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
