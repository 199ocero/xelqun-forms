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
import XqButton from './XqButton.vue';

defineProps<{ title: string; message: string; confirmLabel?: string; cancelLabel?: string }>();
defineEmits<{ confirm: [] }>();
const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <AlertDialogRoot v-model:open="open">
        <AlertDialogPortal>
            <AlertDialogOverlay class="inset-0 bg-black/50 fixed z-50" />
            <AlertDialogContent
                class="max-w-sm p-5 shadow-lg gap-3 fixed top-1/2 left-1/2 z-50 grid w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 rounded-xq-lg border border-xq-border bg-xq-popover text-xq-popover-foreground"
            >
                <AlertDialogTitle class="text-sm font-semibold">{{ title }}</AlertDialogTitle>
                <AlertDialogDescription class="text-sm text-xq-muted-foreground">
                    {{ message }}
                </AlertDialogDescription>
                <div class="gap-2 flex justify-end">
                    <AlertDialogCancel as-child>
                        <XqButton type="button" variant="outline" size="sm">
                            {{ cancelLabel ?? 'Cancel' }}
                        </XqButton>
                    </AlertDialogCancel>
                    <AlertDialogAction as-child>
                        <XqButton
                            type="button"
                            variant="destructive"
                            size="sm"
                            @click="$emit('confirm')"
                        >
                            {{ confirmLabel ?? 'Delete' }}
                        </XqButton>
                    </AlertDialogAction>
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
