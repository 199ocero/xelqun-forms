import type SortableType from 'sortablejs';
import { onBeforeUnmount, watch } from 'vue';
import type { Ref } from 'vue';

export interface SortableOptions {
    /** Which children can move. Start with ">" so a nested list's items don't count. */
    draggable: string;
    /** Only this part starts a drag. Without it, the whole item does. */
    handle?: string;
    enabled: () => boolean;
    /** Called with indexes among the draggable items, once the drag ends somewhere new. */
    onMove: (from: number, to: number) => void;
}

/**
 * Drag to reorder. SortableJS loads only when a list can actually be dragged, and
 * move buttons stay next to it, because keyboard users can't drag.
 */
export function useSortable(container: Ref<HTMLElement | null>, options: SortableOptions): void {
    let sortable: SortableType | null = null;

    function destroy(): void {
        sortable?.destroy();
        sortable = null;
    }

    watch(
        [container, options.enabled],
        async ([element, enabled]) => {
            destroy();

            if (!element || !enabled) {
                return;
            }

            const { default: Sortable } = await import('sortablejs');

            if (container.value !== element || !options.enabled() || sortable) {
                return;
            }

            sortable = Sortable.create(element, {
                draggable: options.draggable,
                handle: options.handle,
                filter: 'input, textarea, button:not([data-xq-drag])',
                preventOnFilter: false,
                animation: 150,
                ghostClass: 'opacity-40',
                // The same drag for mouse and touch, and no text dropped into inputs.
                forceFallback: true,
                onEnd({ item, from, oldIndex, newIndex, oldDraggableIndex, newDraggableIndex }) {
                    if (oldDraggableIndex === newDraggableIndex) {
                        return;
                    }

                    // Put the element back: Vue owns this order and moves it from the state.
                    from.insertBefore(
                        item,
                        from.children[oldIndex! + (oldIndex! > newIndex! ? 1 : 0)] ?? null,
                    );

                    options.onMove(oldDraggableIndex!, newDraggableIndex!);
                },
            });
        },
        { immediate: true },
    );

    onBeforeUnmount(destroy);
}
