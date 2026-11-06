import { watch } from 'vue';
import type { ComputedRef } from 'vue';
import { eachNode } from '../core/patch';
import type { SchemaNode } from '../schema/types';
import { useFormStore } from './context';

/**
 * For layouts that show one panel at a time: count each panel's errors, and open
 * the panel holding the field the form wants to focus.
 */
export function usePanelErrors(
    panels: ComputedRef<SchemaNode[]>,
    open: (panel: SchemaNode) => void,
) {
    const store = useFormStore();

    function pathsIn(panel: SchemaNode): string[] {
        const paths: string[] = [];
        eachNode(panel.children ?? [], (child) => child.path && paths.push(child.path));

        return paths;
    }

    watch(store.focusRequest, (request) => {
        const panel =
            request && panels.value.find((candidate) => pathsIn(candidate).includes(request.path));

        if (panel) {
            open(panel);
        }
    });

    return {
        errorCount: (panel: SchemaNode) =>
            pathsIn(panel).filter((path) => store.errors[path]).length,
    };
}
