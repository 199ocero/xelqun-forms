import { router } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted } from 'vue';
import type { FormStore } from '../core/store';

/**
 * Warn before the user leaves with changes, when the form asks for it. The
 * browser's own dialog covers closing the tab, because nothing else may run there.
 */
export function useUnsavedChangesAlert(store: FormStore): void {
    if (!store.schema.unsavedChangesAlert) {
        return;
    }

    let stopListening = (): void => {};

    function warnBeforeUnload(event: BeforeUnloadEvent): void {
        if (store.isDirty()) {
            event.preventDefault();
        }
    }

    onMounted(async () => {
        // Computed fields settle a tick after they mount, and that isn't an edit.
        await nextTick();
        store.markClean();

        window.addEventListener('beforeunload', warnBeforeUnload);
        stopListening = router.on('before', (event) => {
            const { visit } = event.detail;
            const staysOnPage =
                store.processing.value ||
                visit.prefetch ||
                visit.only.length > 0 ||
                (visit.preserveState === true && visit.url.pathname === window.location.pathname);

            if (!staysOnPage && store.isDirty()) {
                if (!window.confirm(store.t('You have unsaved changes. Leave this page?'))) {
                    event.preventDefault();
                }
            }
        });
    });

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', warnBeforeUnload);
        stopListening();
    });
}
