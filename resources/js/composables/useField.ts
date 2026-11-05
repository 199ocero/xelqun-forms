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
    const id = `xq-${store.id}-${path}`.replace(/[^\w-]/g, '-');
    const error = computed(() => store.errors[path] ?? null);
    const hint = computed(() => store.text(node, 'hint'));

    const value = computed<T>({
        get: () => store.get(path),
        set: (next) => store.set(path, next, 'user'),
    });

    return {
        store,
        node,
        path,
        id,
        value,
        error,
        label: computed(() => store.text(node, 'label')),
        hint,
        placeholder: computed(() => store.text(node, 'placeholder')),
        /** The id for aria-describedby: the error when there is one, otherwise the hint. */
        describedBy: computed(() => {
            if (error.value) {
                return `${id}-error`;
            }

            return hint.value ? `${id}-hint` : undefined;
        }),
        loading: computed(() => store.isLoading(node)),
        /** Delayed version of loading, for the spinner: fast requests never show one. */
        showLoading: computed(() => store.showsLoadingIndicator(node)),
        required: computed(() => store.isRequired(node)),
        /** True when read-only too, so a control with no read-only mode can't be changed. */
        disabled: computed(
            () => store.isDisabled(node) || !!node.readOnly || store.processing.value,
        ),
        readOnly: computed(() => !!node.readOnly),
        onBlur: () => store.blur(path),
    };
}
