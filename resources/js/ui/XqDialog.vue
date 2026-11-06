<script setup lang="ts">
import { X } from 'lucide-vue-next';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { useTranslator } from '../composables/context';
import { cn } from '../lib/utils';
import { dialogContentClass, dialogOverlayClass } from './classes';
import XqButton from './XqButton.vue';
import XqDialogHeader from './XqDialogHeader.vue';

const t = useTranslator();
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string | null;
        width?: string | null;
        icon?: string | null;
        iconColor?: string | null;
        alignment?: 'start' | 'center' | null;
        closeButton?: boolean;
        closeOnClickAway?: boolean;
    }>(),
    { closeButton: true, closeOnClickAway: true },
);
const open = defineModel<boolean>('open', { default: false });

const widths: Record<string, string> = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
    '3xl': 'max-w-3xl',
    '4xl': 'max-w-4xl',
    '5xl': 'max-w-5xl',
};

function keepOpenUnlessAllowed(event: Event): void {
    if (!props.closeOnClickAway) {
        event.preventDefault();
    }
}
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay :class="dialogOverlayClass" />
            <DialogContent
                v-bind="description ? {} : { 'aria-describedby': undefined }"
                :class="cn(dialogContentClass, widths[props.width ?? 'md'])"
                @interact-outside="keepOpenUnlessAllowed"
            >
                <XqDialogHeader
                    :icon="icon"
                    :icon-color="iconColor"
                    :alignment="alignment"
                    class="px-6 py-4 min-h-0 overflow-y-auto border-b border-xq-border"
                    :class="closeButton && 'pr-12'"
                >
                    <DialogTitle class="text-base font-semibold text-balance">
                        {{ title }}
                    </DialogTitle>
                    <DialogDescription
                        v-if="description"
                        class="text-sm text-pretty text-xq-muted-foreground"
                    >
                        {{ description }}
                    </DialogDescription>
                </XqDialogHeader>
                <!-- A sticky footer can't leave its parent, so it brings its own space. -->
                <div class="px-6 pt-5 pb-6 has-data-xq-dialog-footer:pb-0 min-h-0 overflow-y-auto">
                    <slot />
                </div>
                <!-- Last, so a modal's first field gets the focus and not this. -->
                <DialogClose v-if="closeButton" as-child>
                    <XqButton
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="top-3.5 right-3.5 absolute text-xq-muted-foreground"
                        :aria-label="t('Close')"
                    >
                        <X />
                    </XqButton>
                </DialogClose>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
