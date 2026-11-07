<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { cn } from '../lib/utils';

/**
 * One highlight that slides between a list's items, passing over the ones
 * between. Put it first inside a `relative` list. It follows `data-highlighted`,
 * so the arrow keys move it the same as the pointer.
 */
const element = ref<HTMLElement>();
const box = ref({ top: 0, left: 0, width: 0, height: 0 });
const isVisible = ref(false);
const isGliding = ref(false);

const HIDE_DELAY = 60;

let observer: MutationObserver | undefined;
let hideTimer: ReturnType<typeof setTimeout> | undefined;

function follow(): void {
    const list = element.value?.parentElement;
    const item = list?.querySelector<HTMLElement>('[data-highlighted]');

    clearTimeout(hideTimer);

    if (!list || !item) {
        // Items drop the highlight for a moment as the pointer crosses between them.
        hideTimer = setTimeout(() => {
            isVisible.value = false;
            isGliding.value = false;
        }, HIDE_DELAY);

        return;
    }

    // Layout offsets, because a menu is scaled while it opens and on-screen sizes would be off.
    // Walked up to the list, because an item's wrapper can be animating and so count as its parent.
    let top = 0;
    let left = 0;

    for (let node: HTMLElement | null = item; node && node !== list;) {
        top += node.offsetTop;
        left += node.offsetLeft;
        node = node.offsetParent as HTMLElement | null;
    }

    isGliding.value = isVisible.value;
    isVisible.value = true;
    box.value = { top, left, width: item.offsetWidth, height: item.offsetHeight };
}

onMounted(() => {
    const list = element.value?.parentElement;

    if (!list) {
        return;
    }

    observer = new MutationObserver(follow);
    observer.observe(list, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['data-highlighted'],
    });
    follow();
});

onBeforeUnmount(() => {
    observer?.disconnect();
    clearTimeout(hideTimer);
});

const style = computed(() => ({
    transform: `translate(${box.value.left}px, ${box.value.top}px)`,
    width: `${box.value.width}px`,
    height: `${box.value.height}px`,
    opacity: isVisible.value ? 1 : 0,
}));
</script>

<template>
    <span
        ref="element"
        aria-hidden="true"
        :class="
            cn(
                'top-0 left-0 pointer-events-none absolute rounded-xq-sm bg-xq-accent',
                isGliding
                    ? 'transition-[transform,width,height,opacity] duration-(--xq-duration-base) ease-(--xq-ease-out)'
                    : 'transition-opacity duration-(--xq-duration-fast)',
            )
        "
        :style="style"
        data-xq-menu-glide
    ></span>
</template>
