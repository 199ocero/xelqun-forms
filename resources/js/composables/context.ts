import { inject, provide } from 'vue';
import type { InjectionKey } from 'vue';
import type { FormStore } from '../core/store';
import { translate } from '../core/translate';

export const FORM_STORE: InjectionKey<FormStore> = Symbol('xelqun-form-store');

/** Heading level for the next section title. Nested sections go one level deeper. */
export const HEADING_LEVEL: InjectionKey<number> = Symbol('xelqun-heading-level');

/** Set inside a FusedGroup, which lists its fields' errors below the whole group. */
export const FUSED_GROUP: InjectionKey<boolean> = Symbol('xelqun-fused-group');

export function provideFormStore(store: FormStore): void {
    provide(FORM_STORE, store);
}

export function useFormStore(): FormStore {
    const store = inject(FORM_STORE, null);

    if (!store) {
        throw new Error('[xelqun] useFormStore() must be used inside <XqForm>.');
    }

    return store;
}

/**
 * The form's translator, or plain English for a control used outside a form.
 */
export function useTranslator(): FormStore['t'] {
    return (
        inject(FORM_STORE, null)?.t ??
        ((text, replacements) => translate(undefined, text, replacements))
    );
}
