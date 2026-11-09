import { onMounted, watch } from 'vue';
import type { Ref } from 'vue';

/**
 * Keep a value in this browser between visits. It's read after mounting, so a
 * page rendered on the server hydrates without a mismatch. Storage can be
 * blocked or full, and then the value just isn't remembered.
 */
export function usePersisted<T>(
    key: string,
    value: Ref<T>,
    isEnabled: () => boolean,
    accepts: (saved: unknown) => saved is T = (saved): saved is T => saved !== null,
): void {
    onMounted(() => {
        if (!isEnabled()) {
            return;
        }

        try {
            const saved = JSON.parse(localStorage.getItem(key) ?? 'null');

            if (accepts(saved)) {
                value.value = saved;
            }
        } catch {
            // Nothing remembered.
        }

        watch(value, (next) => {
            try {
                localStorage.setItem(key, JSON.stringify(next));
            } catch {
                // Not remembered this time.
            }
        });
    });
}
