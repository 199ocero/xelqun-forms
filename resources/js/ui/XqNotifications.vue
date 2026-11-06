<script setup lang="ts">
import { ToastProvider, ToastViewport } from 'reka-ui';
import { notificationManager } from '../core/notifications';
import XqNotificationList from './XqNotificationList.vue';

/**
 * Shows every notification. createXelqunForms mounts it as an app of its own,
 * because Reka keeps toasts in the provider, and a layout that changes between
 * pages would take them with it.
 *
 * It sits above dialogs, and takes pointer events back from an open one, which
 * turns them off for the whole page.
 */
withDefaults(defineProps<{ label?: string }>(), { label: 'Notifications ({hotkey})' });
</script>

<template>
    <ToastProvider :toast-manager="notificationManager" :limit="5">
        <XqNotificationList />
        <ToastViewport
            :label="label"
            data-slot="xq-notifications"
            class="right-4 top-4 gap-2 max-w-sm pointer-events-auto fixed z-[60] flex w-[calc(100%-2rem)] flex-col-reverse outline-none"
        />
    </ToastProvider>
</template>
