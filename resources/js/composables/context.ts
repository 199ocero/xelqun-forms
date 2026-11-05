import { inject, provide } from 'vue';
import type { InjectionKey } from 'vue';
import type { FormStore } from '../core/store';

export const FORM_STORE: InjectionKey<FormStore> = Symbol('xelqun-form-store');

/** Heading level for the next section title. Nested sections go one level deeper. */
export const HEADING_LEVEL: InjectionKey<number> = Symbol('xelqun-heading-level');

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
