import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { watch } from 'vue';
import type { SchemaNode } from '../schema/types';

/**
 * Keeps the open tab or step in the URL. The server reads it back, so the right
 * panel is open on the first render.
 */
export function useQueryStringPanel(
    active: Ref<string>,
    panels: Ref<SchemaNode[]>,
    queryKey: () => string | null | undefined,
): void {
    watch(active, (key) => {
        const name = queryKey();
        const slug = panels.value.find((panel) => panel.key === key)?.props?.slug as
            | string
            | undefined;

        if (!name || !slug) {
            return;
        }

        const url = new URL(window.location.href);

        url.searchParams.set(name, slug);
        router.replace({
            url: `${url.pathname}${url.search}${url.hash}`,
            preserveState: true,
            preserveScroll: true,
        });
    });
}
