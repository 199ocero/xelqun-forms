<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

/**
 * The mark on the current tab, which leans over to whichever tab is hovered or
 * focused and settles back after. Put it first inside a `relative` tab list.
 */
const props = defineProps<{ class?: HTMLAttributes['class']; vertical?: boolean }>();

const element = ref<HTMLElement>();
const left = ref(0);
const width = ref(0);
const top = ref(0);
const height = ref(0);
const isVisible = ref(false);
const isGliding = ref(false);

let hoveredTab: HTMLElement | undefined;
let attributeObserver: MutationObserver | undefined;
let sizeObserver: ResizeObserver | undefined;

function measure(canGlide = true): void {
    const tab = hoveredTab?.isConnected
        ? hoveredTab
        : element.value?.parentElement?.querySelector<HTMLElement>(
              '[aria-current="page"], [aria-selected="true"]',
          );

    isGliding.value = canGlide && isVisible.value && Boolean(tab);
    isVisible.value = Boolean(tab);

    if (tab) {
        left.value = tab.offsetLeft;
        width.value = tab.offsetWidth;
        top.value = tab.offsetTop;
        height.value = tab.offsetHeight;
    }
}

function hoverTab(event: Event): void {
    const tab = (event.target as HTMLElement).closest<HTMLElement>('a, button:not(:disabled)');

    if (tab) {
        hoveredTab = tab;
        measure();
    }
}

function leaveTabs(): void {
    hoveredTab = undefined;
    measure();
}

onMounted(() => {
    const list = element.value?.parentElement;

    if (!list) {
        return;
    }

    list.addEventListener('pointerover', hoverTab);
    list.addEventListener('pointerleave', leaveTabs);
    list.addEventListener('focusin', hoverTab);
    list.addEventListener('focusout', leaveTabs);

    attributeObserver = new MutationObserver(() => measure());
    attributeObserver.observe(list, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['aria-current', 'aria-selected'],
    });

    // Tabs measure nothing while hidden, and their widths shift when a web font arrives.
    sizeObserver = new ResizeObserver(() => measure(false));
    sizeObserver.observe(list);
    document.fonts?.ready.then(() => measure(false));

    measure(false);
});

onBeforeUnmount(() => {
    const list = element.value?.parentElement;

    list?.removeEventListener('pointerover', hoverTab);
    list?.removeEventListener('pointerleave', leaveTabs);
    list?.removeEventListener('focusin', hoverTab);
    list?.removeEventListener('focusout', leaveTabs);
    attributeObserver?.disconnect();
    sizeObserver?.disconnect();
});

// Vertical tabs stack only on wider screens, so follow the tab both ways there.
const style = computed(() =>
    props.vertical
        ? {
              transform: `translate(${left.value}px, ${top.value}px)`,
              width: `${width.value}px`,
              height: `${height.value}px`,
              top: '0px',
              opacity: isVisible.value ? 1 : 0,
          }
        : {
              transform: `translateX(${left.value}px)`,
              width: `${width.value}px`,
              opacity: isVisible.value ? 1 : 0,
          },
);
</script>

<template>
    <span
        ref="element"
        aria-hidden="true"
        :class="
            cn(
                'left-0 pointer-events-none absolute',
                isGliding
                    ? 'transition-[transform,width,height,opacity] duration-(--xq-duration-base) ease-(--xq-ease-out)'
                    : 'transition-opacity duration-(--xq-duration-fast)',
                props.class,
            )
        "
        :style="style"
        data-xq-tab-glide
    ></span>
</template>
