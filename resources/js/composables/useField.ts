import { computed } from 'vue';
import type { SchemaNode } from '../schema/types';
import { useFormStore } from './context';

/**
 * Everything a field component needs: a writable value bound to the form
 * state, its error, and the evaluated required/disabled rules.
 */
export function useField<T = any>(nodeOrPath: SchemaNode | string) {
    const store = useFormStore();
    const node = typeof nodeOrPath === 'string' ? store.nodes.get(nodeOrPath)! : nodeOrPath;
    const path = node.path ?? node.key;

    const value = computed<T>({
        get: () => store.get(path),
        set: (next) => store.set(path, next, 'user'),
    });

    return {
        store,
        node,
        path,
        id: `xq-${store.id}-${path}`.replace(/[^\w-]/g, '-'),
        value,
        error: computed(() => store.errors[path] ?? null),
        required: computed(() => store.isRequired(node)),
        disabled: computed(() => store.isDisabled(node) || store.processing.value),
        onBlur: () => store.blur(path),
    };
}
