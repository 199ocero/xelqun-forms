import type { Plugin } from 'vue';
import { createRegistry } from './components/defaults';
import { REGISTRY } from './composables/registry';
import type { ComponentResolver } from './composables/registry';

export interface XelqunFormsOptions {
    /** Extra or replacement renderers keyed by schema type, e.g. { rating: () => import('./Rating.vue') }. */
    components?: Record<string, ComponentResolver>;
}

export function createXelqunForms(options: XelqunFormsOptions = {}): Plugin {
    return {
        install(app) {
            app.provide(REGISTRY, createRegistry(options.components));
        },
    };
}
