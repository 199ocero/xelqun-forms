<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { ToastClose, ToastDescription, ToastRoot, ToastTitle, useToastManager } from 'reka-ui';
import type { ToastObject } from 'reka-ui';
import { cn } from '../lib/utils';
import XqIcon from './XqIcon.vue';

const { toasts } = useToastManager();

const statusIcons: Record<string, string> = {
    success: 'circle-check',
    danger: 'circle-alert',
    warning: 'triangle-alert',
    info: 'info',
};

function iconFor(toast: ToastObject): string | undefined {
    return toast.data?.icon ?? statusIcons[toast.status ?? ''];
}

const iconClasses: Record<string, string> = {
    success: 'text-xq-success',
    danger: 'text-xq-destructive',
    warning: 'text-xq-warning',
    info: 'text-xq-info',
};
</script>

<template>
    <ToastRoot
        v-for="toast in toasts"
        :key="toast.id"
        :toast="toast"
        class="gap-3 p-3 shadow-lg data-[swipe=cancel]:translate-x-0 flex items-start rounded-xq-md border border-xq-border bg-xq-popover text-xq-popover-foreground outline-none focus-visible:ring-[3px] focus-visible:ring-xq-ring/25 data-[limited]:hidden data-[swipe=end]:translate-x-(--reka-toast-swipe-end-x) data-[swipe=move]:translate-x-(--reka-toast-swipe-move-x) motion-safe:data-[state=closed]:animate-xq-fade-out motion-safe:data-[state=open]:animate-xq-fade-in"
    >
        <XqIcon
            v-if="iconFor(toast)"
            :name="iconFor(toast)!"
            :class="cn('mt-0.5', iconClasses[toast.status ?? ''])"
        />
        <div class="min-w-0 flex-1 wrap-break-word">
            <ToastTitle v-if="toast.title" class="text-sm font-medium">
                {{ toast.title }}
            </ToastTitle>
            <ToastDescription v-if="toast.description" class="text-sm text-xq-muted-foreground">
                {{ toast.description }}
            </ToastDescription>
        </div>
        <ToastClose
            :aria-label="toast.data?.closeLabel ?? 'Close'"
            class="rounded-xq-sm text-xq-muted-foreground outline-none hover:text-xq-foreground focus-visible:ring-[3px] focus-visible:ring-xq-ring/25"
        >
            <X class="size-4" aria-hidden="true" />
        </ToastClose>
    </ToastRoot>
</template>
