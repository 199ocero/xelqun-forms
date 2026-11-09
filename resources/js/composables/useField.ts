import { computed } from 'vue';
import type { SchemaNode } from '../schema/types';
import type { FormStore } from '../core/store';
import { useFormStore } from './context';

/**
 * The id of a field's control. Its error and helper text hang off this.
 */
export function fieldId(store: FormStore, path: string): string {
    return `xq-${store.id}-${path}`.replace(/[^\w-]/g, '-');
}

/**
 * Everything a field component needs: a writable value bound to the form
 * state, its error, and the evaluated required/disabled rules.
 */
export function useField<T = any>(nodeOrPath: SchemaNode | string) {
    const store = useFormStore();
    const node = typeof nodeOrPath === 'string' ? store.nodes.get(nodeOrPath)! : nodeOrPath;
    const path = node.path ?? node.key;
    const id = fieldId(store, path);
    const error = computed(() => store.errors[path] ?? null);
    const helperText = computed(() => store.text(node, 'helperText'));

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
        hint: computed(() => store.text(node, 'hint')),
        helperText,
        placeholder: computed(() => store.text(node, 'placeholder')),
        /** The id for aria-describedby: the error when there is one, otherwise the helper text. */
        describedBy: computed(() => {
            if (error.value) {
                return `${id}-error`;
            }

            return helperText.value ? `${id}-helper` : undefined;
        }),
        loading: computed(() => store.isLoading(node)),
        /** Delayed version of loading, for the spinner: fast requests never show one. */
        showLoading: computed(() => store.showsLoadingIndicator(node)),
        required: computed(() => store.isRequired(node)),
        /** Whether the label shows an asterisk, which markAsRequired() can change. */
        showsRequired: computed(() => node.markedAsRequired ?? store.isRequired(node)),
        /** True when read-only too, so a control with no read-only mode can't be changed. */
        disabled: computed(
            () => store.isDisabled(node) || !!node.readOnly || store.processing.value,
        ),
        readOnly: computed(() => !!node.readOnly),
        onBlur: () => store.blur(path),
    };
}
