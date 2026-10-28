import { onScopeDispose } from 'vue';
import { createFormStore } from '../core/store';
import type { FormStore, FormStoreOptions } from '../core/store';
import type { FormSchema } from '../schema/types';

/**
 * Create a form store from the schema prop sent by the server.
 * Pass the result to <XqForm :form="form">.
 */
export function useXqForm(schema: FormSchema, options: FormStoreOptions = {}): FormStore {
    const store = createFormStore(schema, options);

    onScopeDispose(() => store.dispose());

    return store;
}
