import { inject, provide } from 'vue';
import type { InjectionKey } from 'vue';
import type { FormStore } from '../core/store';

export const FORM_STORE: InjectionKey<FormStore> = Symbol('xelqun-form-store');

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
