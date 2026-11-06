<script setup lang="ts">
import { ArrowDown, ArrowUp, ChevronDown, Copy, Trash2 } from 'lucide-vue-next';
import { cn } from '../../lib/utils';
import XqButton from '../../ui/XqButton.vue';
import { useTranslator } from '../../composables/context';

const t = useTranslator();
defineProps<{
    label: string;
    index: number;
    count: number;
    disabled?: boolean;
    orderable?: boolean;
    canClone?: boolean;
    showClone?: boolean;
    canRemove?: boolean;
    showRemove?: boolean;
    collapsible?: boolean;
    collapsed?: boolean;
}>();
defineEmits<{ move: [direction: -1 | 1]; clone: []; remove: []; toggle: [] }>();
</script>

<template>
    <div class="gap-0.5 flex items-center">
        <slot />
        <template v-if="orderable && count > 1">
            <XqButton
                type="button"
                variant="ghost"
                size="icon-sm"
                :disabled="disabled || index === 0"
                :aria-label="t('Move :label :number up', { label, number: index + 1 })"
                data-xq-move="-1"
                @click="$emit('move', -1)"
            >
                <ArrowUp />
            </XqButton>
            <XqButton
                type="button"
                variant="ghost"
                size="icon-sm"
                :disabled="disabled || index === count - 1"
                :aria-label="t('Move :label :number down', { label, number: index + 1 })"
                data-xq-move="1"
                @click="$emit('move', 1)"
            >
                <ArrowDown />
            </XqButton>
        </template>
        <XqButton
            v-if="showClone"
            type="button"
            variant="ghost"
            size="icon-sm"
            :disabled="!canClone"
            :aria-label="t('Copy :label :number', { label, number: index + 1 })"
            data-xq-clone
            @click="$emit('clone')"
        >
            <Copy />
        </XqButton>
        <XqButton
            v-if="showRemove"
            type="button"
            variant="ghost"
            size="icon-sm"
            class="text-xq-muted-foreground hover:text-xq-destructive"
            :disabled="!canRemove"
            :aria-label="t('Remove :label :number', { label, number: index + 1 })"
            data-xq-remove
            @click="$emit('remove')"
        >
            <Trash2 />
        </XqButton>
        <XqButton
            v-if="collapsible"
            type="button"
            variant="ghost"
            size="icon-sm"
            :aria-expanded="!collapsed"
            :aria-label="
                t(collapsed ? 'Expand :label :number' : 'Collapse :label :number', {
                    label,
                    number: index + 1,
                })
            "
            data-xq-collapse
            @click="$emit('toggle')"
        >
            <ChevronDown
                :class="
                    cn(
                        'transition-transform motion-reduce:transition-none',
                        collapsed && '-rotate-90',
                    )
                "
            />
        </XqButton>
    </div>
</template>
