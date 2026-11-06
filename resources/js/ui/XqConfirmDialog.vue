<script setup lang="ts">
import {
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'reka-ui';
import { useTranslator } from '../composables/context';
import { cn } from '../lib/utils';
import { dialogContentClass, dialogOverlayClass } from './classes';
import XqButton from './XqButton.vue';
import XqDialogFooter from './XqDialogFooter.vue';
import XqDialogHeader from './XqDialogHeader.vue';

const t = useTranslator();

withDefaults(
    defineProps<{
        title: string;
        message?: string | null;
        confirmLabel?: string | null;
        cancelLabel?: string | null;
        confirmVariant?: 'default' | 'destructive';
        icon?: string | null;
        iconColor?: string | null;
        alignment?: 'start' | 'center' | null;
        footerAlignment?: 'start' | 'center' | 'end' | 'full' | null;
    }>(),
    {
        message: undefined,
        confirmLabel: undefined,
        cancelLabel: undefined,
        confirmVariant: 'destructive',
        icon: 'triangle-alert',
        iconColor: undefined,
        alignment: 'center',
        footerAlignment: 'full',
    },
);
defineEmits<{ confirm: [] }>();
const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <AlertDialogRoot v-model:open="open">
        <AlertDialogPortal>
            <AlertDialogOverlay :class="dialogOverlayClass" />
            <AlertDialogContent
                v-bind="message ? {} : { 'aria-describedby': undefined }"
                :class="cn(dialogContentClass, 'max-w-sm p-6 gap-5 overflow-y-auto')"
            >
                <XqDialogHeader
                    :icon="icon"
                    :icon-color="
                        iconColor ?? (confirmVariant === 'destructive' ? 'danger' : 'primary')
                    "
                    :alignment="alignment"
                >
                    <AlertDialogTitle class="text-base font-semibold text-balance">
                        {{ title }}
                    </AlertDialogTitle>
                    <AlertDialogDescription
                        v-if="message"
                        class="text-sm text-pretty text-xq-muted-foreground"
                    >
                        {{ message }}
                    </AlertDialogDescription>
                </XqDialogHeader>
                <XqDialogFooter :alignment="footerAlignment">
                    <AlertDialogCancel as-child>
                        <XqButton type="button" variant="outline" size="sm">
                            {{ cancelLabel ?? t('Cancel') }}
                        </XqButton>
                    </AlertDialogCancel>
                    <AlertDialogAction as-child>
                        <XqButton
                            type="button"
                            :variant="confirmVariant"
                            size="sm"
                            @click="$emit('confirm')"
                        >
                            {{ confirmLabel ?? t('Delete') }}
                        </XqButton>
                    </AlertDialogAction>
                </XqDialogFooter>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
